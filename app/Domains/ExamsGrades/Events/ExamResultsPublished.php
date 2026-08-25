<?php

namespace App\Domains\ExamsGrades\Events;

class ExamResultsPublished
{
    public function __construct(
        public int $examId,
        public int $classId,
        public string $examName,
        public ?string $examDate,
    ) {}
}
