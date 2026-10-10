<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use App\Domains\Finance\Enums\FeeItemType;
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
            throw ValidationException::withMessages(['student_id' => __('finance.error_adjustment_student')]);
        }

        $value = $data['value'] ?? null;
        if ($value === null || $value === '' || (float) $value <= 0) {
            throw ValidationException::withMessages(['value' => __('finance.error_value_positive')]);
        }

        $basis = FeeAdjustmentBasis::from((string) ($data['basis'] ?? FeeAdjustmentBasis::Percent->value));
        if ($basis === FeeAdjustmentBasis::Percent && (float) $value > 100) {
            throw ValidationException::withMessages(['value' => __('finance.error_percent_max')]);
        }

        $appliesTo = FeeAdjustmentAppliesTo::from((string) ($data['applies_to'] ?? FeeAdjustmentAppliesTo::AllItems->value));
        $itemTypes = array_values(array_unique(array_filter(
            array_map('strval', (array) ($data['item_types'] ?? [])),
            fn (string $type) => FeeItemType::tryFrom($type) !== null,
        )));
        if ($appliesTo === FeeAdjustmentAppliesTo::ItemTypes && $itemTypes === []) {
            throw ValidationException::withMessages(['item_types' => __('finance.error_adjustment_item_types')]);
        }

        $payload = [
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'type' => FeeAdjustmentType::from((string) ($data['type'] ?? FeeAdjustmentType::Other->value)),
            'basis' => $basis,
            'value' => $value,
            'applies_to' => $appliesTo,
            'item_types' => $appliesTo === FeeAdjustmentAppliesTo::ItemTypes ? $itemTypes : null,
            'approved_by' => isset($data['approved_by']) ? (int) $data['approved_by'] : null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
            'status' => FeeAdjustmentStatus::from((string) ($data['status'] ?? FeeAdjustmentStatus::Draft->value)),
        ];

        if ($payload['status'] === FeeAdjustmentStatus::Approved && ! $payload['approved_by']) {
            throw ValidationException::withMessages(['approved_by' => __('finance.error_approver')]);
        }

        if ($adjustment === null) {
            return FeeAdjustment::query()->create($payload);
        }

        $adjustment->fill($payload);
        $adjustment->save();

        return $adjustment->refresh();
    }
}
