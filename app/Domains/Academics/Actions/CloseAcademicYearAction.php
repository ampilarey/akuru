<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\TermStatus;
use App\Domains\Academics\Models\AcademicYear;
use RuntimeException;

class CloseAcademicYearAction
{
    public function execute(AcademicYear $year): AcademicYear
    {
        $openTerms = $year->termRecords()
            ->where('status', '!=', TermStatus::Closed->value)
            ->count();

        if ($openTerms > 0) {
            throw new RuntimeException('All terms must be closed before closing the academic year.');
        }

        $year->forceFill([
            'status' => AcademicYearStatus::Closed,
            'is_current' => false,
        ])->save();

        return $year->refresh();
    }
}
