<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Jobs\RenderReportCardJob;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateReportCardsAction
{
    /**
     * @return Collection<int, ReportCard>
     */
    public function execute(int $classId, int $termId, ?int $templateId = null, string $locale = 'en', ?int $actorId = null, bool $queue = true): Collection
    {
        $template = $this->resolveTemplate($classId, $templateId);
        $term = DB::table('terms')->where('id', $termId)->first();
        if ($term === null) {
            throw ValidationException::withMessages(['term_id' => 'Term not found.']);
        }

        $asOf = $term->end_date ?? now()->toDateString();
        $studentIds = DB::table('class_student')
            ->where('class_id', $classId)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('enrolled_at')->orWhereDate('enrolled_at', '<=', $asOf);
            })
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('left_at')->orWhereDate('left_at', '>=', $asOf);
            })
            ->pluck('student_id');

        $cards = collect();
        foreach ($studentIds as $studentId) {
            $existing = ReportCard::query()
                ->where('student_id', $studentId)
                ->where('term_id', $termId)
                ->first();

            if ($existing?->status === ReportCardStatus::Published) {
                continue;
            }

            $card = ReportCard::query()->updateOrCreate(
                [
                    'student_id' => (int) $studentId,
                    'term_id' => $termId,
                ],
                [
                    'class_id' => $classId,
                    'template_id' => $template->id,
                    'status' => ReportCardStatus::Draft,
                ],
            );

            if ($queue) {
                RenderReportCardJob::dispatch($card->id, $locale, $actorId);
                $cards->push($card->fresh());
            } else {
                $cards->push($this->renderOne($card->id, $locale, $actorId));
            }
        }

        return $cards->values();
    }

    public function renderOne(int $reportCardId, string $locale = 'en', ?int $actorId = null): ReportCard
    {
        $card = ReportCard::query()->with(['template', 'comments'])->findOrFail($reportCardId);
        if ($card->status === ReportCardStatus::Published) {
            throw ValidationException::withMessages(['status' => 'Published report cards cannot be regenerated.']);
        }

        $payload = app(AssembleReportCardDataAction::class)->execute($card, $locale);
        $html = app(DocumentRendererInterface::class)->render('report-card', $payload);
        $document = app(StoreGeneratedDocumentAction::class)->execute(
            $card->getMorphClass(),
            $card->id,
            'report_card',
            sprintf('Report card — %s', $payload['student']['name'] ?? $card->student_id),
            $html,
            'html',
            $actorId,
        );

        $card->fill([
            'status' => ReportCardStatus::Ready,
            'document_id' => $document->id,
            'generated_at' => now(),
        ]);
        $card->save();

        return $card->refresh();
    }

    private function resolveTemplate(int $classId, ?int $templateId): ReportCardTemplate
    {
        if ($templateId) {
            return ReportCardTemplate::query()->where('active', true)->findOrFail($templateId);
        }

        $templates = ReportCardTemplate::query()->where('active', true)->orderBy('id')->get();
        $match = $templates->first(function (ReportCardTemplate $template) use ($classId) {
            $ids = $template->applies_to ?? [];

            return $ids !== [] && in_array($classId, array_map('intval', $ids), true);
        });

        return $match ?? $templates->first(fn (ReportCardTemplate $template) => ($template->applies_to ?? []) === [])
            ?? throw ValidationException::withMessages(['template_id' => 'No active report card template applies to this class.']);
    }
}
