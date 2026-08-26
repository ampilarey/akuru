<?php

namespace App\Domains\ExamsGrades\Contracts;

interface GradeItemContract
{
    public function key(): string;

    public function label(): string;

    public function source(): string;

    public function maxScore(): ?float;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{score: float|null, max_score: float|null, status: string|null, is_absent: bool, is_exempt: bool}>
     */
    public function results(array $studentIds): array;
}
