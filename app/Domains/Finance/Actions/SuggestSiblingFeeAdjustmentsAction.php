<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use App\Domains\Finance\Models\FeeAdjustment;
use App\Domains\People\Actions\ListStudentsSharingFinancialGuardianAction;
use Illuminate\Support\Collection;

class SuggestSiblingFeeAdjustmentsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId, int $yearId): Collection
    {
        $siblings = app(ListStudentsSharingFinancialGuardianAction::class)->execute($studentId);
        if ($siblings->isEmpty()) {
            return collect();
        }

        $existing = FeeAdjustment::query()
            ->where('academic_year_id', $yearId)
            ->where('type', FeeAdjustmentType::SiblingDiscount->value)
            ->where('status', '!=', FeeAdjustmentStatus::Revoked->value)
            ->whereIn('student_id', $siblings->pluck('student_id')->push($studentId))
            ->pluck('student_id');

        return $siblings
            ->reject(fn (array $row) => $existing->contains($row['student_id']))
            ->map(fn (array $row) => $row + [
                'suggested_type' => FeeAdjustmentType::SiblingDiscount->value,
                'suggested_basis' => 'percent',
                'suggested_value' => 10,
                'reason' => 'Shares financially-responsible guardian '.$row['guardian_name'],
            ])
            ->values();
    }
}
