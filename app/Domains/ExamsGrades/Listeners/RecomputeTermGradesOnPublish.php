<?php

namespace App\Domains\ExamsGrades\Listeners;

use App\Domains\ExamsGrades\Actions\ComputeTermGradesAction;
use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use App\Domains\ExamsGrades\Models\Exam;

class RecomputeTermGradesOnPublish
{
    public function handle(ExamResultsPublished $event): void
    {
        $exam = Exam::query()->find($event->examId);
        if ($exam === null) {
            return;
        }

        app(ComputeTermGradesAction::class)->execute($exam->class_id, $exam->subject_id, $exam->term_id);
    }
}
