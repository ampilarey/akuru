<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Jobs\RenderReportCardJob;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\ReportCardRevision;
use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateReportCardsAction
{
    /**
     * Held for the life of this action so the assembler's per-run memo of the
     * term, year, class and template survives the loop. A queued single-card
     * render gets a fresh instance and loses nothing by it.
     */
    private ?AssembleReportCardDataAction $assembler = null;

    /**
     * Generate the class's cards for the term.
     *
     * A published card is left alone unless `$reason` is given: S3.6 says
     * *"regeneration allowed until published; after, new version with
     * audit"*, and the reason is the audit (ADR-038). With one, the card's
     * document is re-rendered and replaced in place — same card, same link,
     * still published — and a `report_card_revisions` row records the
     * document it superseded, who asked and why.
     *
     * @return Collection<int, ReportCard>
     */
    public function execute(int $classId, int $termId, ?int $templateId = null, string $locale = 'en', ?int $actorId = null, bool $queue = true, ?string $reason = null): Collection
    {
        $reason = trim((string) $reason) === '' ? null : trim((string) $reason);
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

        // One query for the whole class instead of one per student.
        //
        // This loop ran `where(student_id)->first()` per student and then
        // `updateOrCreate`, which is a second select and a write each. A class
        // of 30 was ~90 queries and a whole school in one sitting ran to
        // thousands — on the operation the operator notes already single out
        // as needing a queue worker, which is a reason to make it cheaper
        // rather than to leave it.
        $existingByStudent = ReportCard::query()
            ->where('term_id', $termId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $cards = collect();
        foreach ($studentIds as $studentId) {
            $existing = $existingByStudent->get($studentId);

            if ($existing?->status === ReportCardStatus::Published) {
                if ($reason === null) {
                    continue;
                }

                // Not through updateOrCreate: that would reset the status to
                // draft and take the card off the portal while it re-renders.
                $card = $existing;
            } else {
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
            }

            if ($queue) {
                RenderReportCardJob::dispatch($card->id, $locale, $actorId, $reason);
                $cards->push($card->fresh());
            } else {
                $cards->push($this->renderOne($card->id, $locale, $actorId, $reason));
            }
        }

        return $cards->values();
    }

    /**
     * Render one card. A published card renders only with a reason, and the
     * render then leaves a revision row behind (ADR-038).
     */
    public function renderOne(int $reportCardId, string $locale = 'en', ?int $actorId = null, ?string $reason = null): ReportCard
    {
        $card = ReportCard::query()->with(['template', 'comments'])->findOrFail($reportCardId);
        $reason = trim((string) $reason) === '' ? null : trim((string) $reason);
        $published = $card->status === ReportCardStatus::Published;

        if ($published && $reason === null) {
            throw ValidationException::withMessages([
                'status' => 'A published report card is regenerated only with a reason, which is recorded against it.',
            ]);
        }

        $payload = ($this->assembler ??= app(AssembleReportCardDataAction::class))->execute($card, $locale);
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

        if ($published) {
            // The old document stays in Media: the revision points at it, so
            // what a family downloaded last week can still be read back.
            ReportCardRevision::query()->create([
                'report_card_id' => $card->id,
                'academic_year_id' => (int) DB::table('terms')->where('id', $card->term_id)->value('academic_year_id'),
                'term_id' => $card->term_id,
                'superseded_document_id' => $card->document_id,
                'document_id' => $document->id,
                'actor_id' => $actorId,
                'reason' => $reason,
            ]);
        }

        $card->fill([
            // A published card stays published: the corrected version is what
            // the portal serves from this moment, at the same link.
            'status' => $published ? ReportCardStatus::Published : ReportCardStatus::Ready,
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
