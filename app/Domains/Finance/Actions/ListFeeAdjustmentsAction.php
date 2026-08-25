<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FeeAdjustment;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

class ListFeeAdjustmentsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $query = FeeAdjustment::query()->orderByDesc('id');
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        $rows = $query->get();
        $names = app(ListStudentsByIdsAction::class)->execute($rows->pluck('student_id')->all())->keyBy('id');

        return $rows->map(fn (FeeAdjustment $row) => [
            'id' => $row->id,
            'student_id' => $row->student_id,
            'student_name' => $names[$row->student_id]['name'] ?? null,
            'academic_year_id' => $row->academic_year_id,
            'type' => $row->type?->value,
            'basis' => $row->basis?->value,
            'value' => $row->value,
            'applies_to' => $row->applies_to?->value,
            'item_types' => $row->item_types,
            'valid_from' => $row->valid_from?->toDateString(),
            'valid_until' => $row->valid_until?->toDateString(),
            'status' => $row->status?->value,
            'notes' => $row->notes,
        ]);
    }
}
