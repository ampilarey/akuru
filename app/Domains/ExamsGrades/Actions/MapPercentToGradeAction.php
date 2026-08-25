<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\GradeScale;

class MapPercentToGradeAction
{
    /**
     * @return array{grade: string|null, grade_point: float|null}
     */
    public function execute(float $percent, ?GradeScale $scale = null): array
    {
        $scale ??= GradeScale::query()->where('is_default', true)->first();
        if ($scale === null) {
            return ['grade' => null, 'grade_point' => null];
        }

        $bands = collect($scale->bands ?? [])
            ->sortByDesc(fn (array $band) => (float) ($band['min'] ?? -1))
            ->values();

        foreach ($bands as $band) {
            $min = $band['min'] ?? null;
            if ($min !== null && $percent + 0.0001 >= (float) $min) {
                return [
                    'grade' => (string) ($band['grade'] ?? $band['level'] ?? ''),
                    'grade_point' => isset($band['point']) ? (float) $band['point'] : null,
                ];
            }
        }

        $last = $bands->last();

        return [
            'grade' => $last['grade'] ?? $last['level'] ?? null,
            'grade_point' => isset($last['point']) ? (float) $last['point'] : null,
        ];
    }
}
