<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use App\Domains\Finance\Models\FeeAdjustment;
use Illuminate\Validation\ValidationException;

class SaveFeeAdjustmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?FeeAdjustment $adjustment = null): FeeAdjustment
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($studentId < 1 || $yearId < 1) {
            throw ValidationException::withMessages(['student_id' => 'Student and academic year are required.']);
        }

        $value = $data['value'] ?? null;
        if ($value === null || $value === '' || (float) $value <= 0) {
            throw ValidationException::withMessages(['value' => 'Value must be greater than zero.']);
        }

        $basis = FeeAdjustmentBasis::from((string) ($data['basis'] ?? FeeAdjustmentBasis::Percent->value));
        if ($basis === FeeAdjustmentBasis::Percent && (float) $value > 100) {
            throw ValidationException::withMessages(['value' => 'Percent cannot exceed 100.']);
        }

        $payload = [
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'type' => FeeAdjustmentType::from((string) ($data['type'] ?? FeeAdjustmentType::Other->value)),
            'basis' => $basis,
            'value' => $value,
            'applies_to' => FeeAdjustmentAppliesTo::from((string) ($data['applies_to'] ?? FeeAdjustmentAppliesTo::AllItems->value)),
            'item_types' => $data['item_types'] ?? null,
            'approved_by' => isset($data['approved_by']) ? (int) $data['approved_by'] : null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
            'status' => FeeAdjustmentStatus::from((string) ($data['status'] ?? FeeAdjustmentStatus::Draft->value)),
        ];

        if ($payload['status'] === FeeAdjustmentStatus::Approved && ! $payload['approved_by']) {
            throw ValidationException::withMessages(['approved_by' => 'Approved adjustments need an approver.']);
        }

        if ($adjustment === null) {
            return FeeAdjustment::query()->create($payload);
        }

        $adjustment->fill($payload);
        $adjustment->save();

        return $adjustment->refresh();
    }
}
