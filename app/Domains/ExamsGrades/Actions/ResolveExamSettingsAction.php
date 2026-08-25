<?php

namespace App\Domains\ExamsGrades\Actions;

use Illuminate\Support\Facades\DB;

class ResolveExamSettingsAction
{
    /**
     * @return array{max_per_class_per_day: int}
     */
    public function execute(): array
    {
        $value = DB::table('settings')->where('key', 'exams_max_per_class_per_day')->value('value');

        return [
            'max_per_class_per_day' => max(1, (int) ($value ?? 1)),
        ];
    }
}
