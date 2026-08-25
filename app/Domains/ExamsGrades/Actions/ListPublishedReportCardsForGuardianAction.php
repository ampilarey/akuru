<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListPublishedReportCardsForGuardianAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $guardianUserId, ?int $studentId = null): Collection
    {
        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($guardianUserId);
        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($studentId !== null && ! in_array($studentId, $childIds, true)) {
            return collect();
        }

        $ids = $studentId !== null ? [$studentId] : $childIds;
        if ($ids === []) {
            return collect();
        }

        $cards = ReportCard::query()
            ->whereIn('student_id', $ids)
            ->where('status', ReportCardStatus::Published)
            ->orderByDesc('published_at')
            ->get();

        $terms = DB::table('terms')->whereIn('id', $cards->pluck('term_id'))->pluck('name', 'id');
        $students = DB::table('students')->whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])->keyBy('id');

        return $cards->map(function (ReportCard $card) use ($terms, $students) {
            $student = $students[$card->student_id] ?? null;

            return [
                'id' => $card->id,
                'student_id' => $card->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'term_name' => $terms[$card->term_id] ?? null,
                'published_at' => $card->published_at?->toDateTimeString(),
                'document_id' => $card->document_id,
            ];
        })->values();
    }
}
