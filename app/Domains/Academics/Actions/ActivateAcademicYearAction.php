<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AcademicYear;
use RuntimeException;

class ActivateAcademicYearAction
{
    public function execute(AcademicYear $year): AcademicYear
    {
        $otherActive = AcademicYear::query()
            ->where('status', AcademicYearStatus::Active)
            ->where('id', '!=', $year->id)
            ->exists();

        if ($otherActive) {
            throw new RuntimeException('Another academic year is already active. Close it before activating this one.');
        }

        $year->forceFill([
            'status' => AcademicYearStatus::Active,
            'is_current' => true,
        ])->save();

        return $year->refresh();
    }
}
