<?php

namespace App\Domains\ExamsGrades\DTOs;

use App\Domains\ExamsGrades\Contracts\GradeItemContract;

final class GradeItem implements GradeItemContract
{
    /**
     * @param  array<int, array{score: float|null, max_score: float|null, status: string|null, is_absent: bool, is_exempt: bool}>  $resultsByStudent
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $source,
        private readonly ?float $maxScore,
        private readonly array $resultsByStudent,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function maxScore(): ?float
    {
        return $this->maxScore;
    }

    public function results(array $studentIds): array
    {
        $out = [];
        foreach ($studentIds as $studentId) {
            $id = (int) $studentId;
            if (isset($this->resultsByStudent[$id])) {
                $out[$id] = $this->resultsByStudent[$id];
            }
        }

        return $out;
    }
}
