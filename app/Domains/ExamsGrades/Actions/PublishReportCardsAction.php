<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Events\ReportCardsPublished;
use App\Domains\ExamsGrades\Models\ReportCard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishReportCardsAction
{
    /**
     * @return Collection<int, ReportCard>
     */
    public function execute(int $classId, int $termId): Collection
    {
        $cards = ReportCard::query()
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->where('status', ReportCardStatus::Ready)
            ->get();

        if ($cards->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'No ready report cards to publish.']);
        }

        $now = now();
        ReportCard::query()->whereIn('id', $cards->pluck('id'))->update([
            'status' => ReportCardStatus::Published,
            'published_at' => $now,
        ]);

        $term = DB::table('terms')->where('id', $termId)->first();

        event(new ReportCardsPublished(
            classId: $classId,
            termId: $termId,
            reportCardIds: $cards->pluck('id')->map(fn ($id) => (int) $id)->all(),
            studentIds: $cards->pluck('student_id')->map(fn ($id) => (int) $id)->all(),
            termName: $term->name ?? 'this term',
        ));

        return ReportCard::query()->whereIn('id', $cards->pluck('id'))->get();
    }
}
