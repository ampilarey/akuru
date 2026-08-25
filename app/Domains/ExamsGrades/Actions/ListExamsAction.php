<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListExamsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $exams = Exam::query()
            ->with('examType')
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderByDesc('exam_date')
            ->orderBy('name')
            ->get();

        $classIds = $exams->pluck('class_id')->unique()->all();
        $subjectIds = $exams->pluck('subject_id')->unique()->all();
        $classes = $classIds === []
            ? collect()
            : DB::table('classes')->whereIn('id', $classIds)->get(['id', 'name', 'section'])->keyBy('id');
        $subjects = $subjectIds === []
            ? collect()
            : DB::table('subjects')->whereIn('id', $subjectIds)->get(['id', 'name'])->keyBy('id');

        return $exams->map(function (Exam $exam) use ($classes, $subjects) {
            $class = $classes[$exam->class_id] ?? null;
            $subject = $subjects[$exam->subject_id] ?? null;

            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'academic_year_id' => $exam->academic_year_id,
                'term_id' => $exam->term_id,
                'class_id' => $exam->class_id,
                'class_name' => trim(($class->name ?? '').' '.($class->section ?? '')),
                'subject_id' => $exam->subject_id,
                'subject_name' => $subject->name ?? null,
                'exam_type_id' => $exam->exam_type_id,
                'exam_type' => $exam->examType?->name,
                'exam_date' => $exam->exam_date?->toDateString(),
                'start_time' => $exam->start_time?->format('H:i'),
                'end_time' => $exam->end_time?->format('H:i'),
                'room_id' => $exam->room_id,
                'max_marks' => $exam->max_marks,
                'weight_override' => $exam->weight_override,
                'instructions' => $exam->instructions,
                'status' => $exam->status->value,
                'published_at' => $exam->published_at?->toDateTimeString(),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $exams
     * @return Collection<int, array<string, mixed>>
     */
    public function ungraded(Collection $exams): Collection
    {
        $today = now()->toDateString();

        return $exams
            ->filter(fn (array $row) => $row['status'] === ExamStatus::MarksEntry->value
                && $row['exam_date'] !== null
                && $row['exam_date'] < $today)
            ->values();
    }
}
