<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListReportCardsAction
{
    /**
     * @param  array{class_id?: int|null, term_id?: int|null, status?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $rows = ReportCard::query()
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->when($filters['term_id'] ?? null, fn ($query, $id) => $query->where('term_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');
        $classes = DB::table('classes')
            ->whereIn('id', $rows->pluck('class_id')->unique())
            ->get(['id', 'name', 'section'])
            ->keyBy('id');
        $terms = DB::table('terms')
            ->whereIn('id', $rows->pluck('term_id')->unique())
            ->get(['id', 'name'])
            ->keyBy('id');
        $templates = ReportCardTemplate::query()
            ->whereIn('id', $rows->pluck('template_id')->unique())
            ->get()
            ->keyBy('id');

        return $rows->map(function (ReportCard $card) use ($students, $classes, $terms, $templates) {
            $student = $students[$card->student_id] ?? null;
            $class = $classes[$card->class_id] ?? null;
            $term = $terms[$card->term_id] ?? null;

            return [
                'id' => $card->id,
                'student_id' => $card->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'student_number' => $student->student_id ?? null,
                'class_id' => $card->class_id,
                'class_name' => trim(($class->name ?? '').' '.($class->section ?? '')),
                'term_id' => $card->term_id,
                'term_name' => $term->name ?? null,
                'template' => $templates[$card->template_id]->name ?? null,
                'status' => $card->status?->value,
                'document_id' => $card->document_id,
                'generated_at' => $card->generated_at?->toDateTimeString(),
                'published_at' => $card->published_at?->toDateTimeString(),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function unpublished(): Collection
    {
        return $this->execute()->filter(
            fn (array $row) => in_array($row['status'], [ReportCardStatus::Draft->value, ReportCardStatus::Ready->value], true),
        )->values();
    }
}
