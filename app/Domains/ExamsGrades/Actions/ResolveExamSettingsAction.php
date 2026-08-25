<?php

namespace App\Domains\ExamsGrades\Actions;

use Illuminate\Support\Facades\DB;

class ResolveExamSettingsAction
{
    /**
     * @return array{max_per_class_per_day: int, exclude_absent: bool}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['exams_max_per_class_per_day', 'exams_exclude_absent'])
            ->pluck('value', 'key');

        $exclude = strtolower(trim((string) ($rows['exams_exclude_absent'] ?? '0')));

        return [
            'max_per_class_per_day' => max(1, (int) ($rows['exams_max_per_class_per_day'] ?? 1)),
            'exclude_absent' => in_array($exclude, ['1', 'true', 'yes'], true),
        ];
    }
}
