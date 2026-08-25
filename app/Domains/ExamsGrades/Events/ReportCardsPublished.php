<?php

namespace App\Domains\ExamsGrades\Events;

class ReportCardsPublished
{
    /**
     * @param  list<int>  $reportCardIds
     * @param  list<int>  $studentIds
     */
    public function __construct(
        public int $classId,
        public int $termId,
        public array $reportCardIds,
        public array $studentIds,
        public string $termName,
    ) {}
}
