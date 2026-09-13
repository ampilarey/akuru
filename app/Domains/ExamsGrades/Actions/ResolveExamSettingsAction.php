<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

class ResolveExamSettingsAction
{
    public function __construct(private SettingsRepositoryInterface $settings) {}

    /**
     * @return array{max_per_class_per_day: int, exclude_absent: bool, compute_rank: bool}
     */
    public function execute(): array
    {
        $rows = collect($this->settings->many(array_fill_keys([
            'exams_max_per_class_per_day',
            'exams_exclude_absent',
            'exams_compute_rank',
        ], null)));

        return [
            'max_per_class_per_day' => max(1, (int) ($rows['exams_max_per_class_per_day'] ?? 1)),
            'exclude_absent' => $this->truthy($rows['exams_exclude_absent'] ?? '0'),
            'compute_rank' => $this->truthy($rows['exams_compute_rank'] ?? '1'),
        ];
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
