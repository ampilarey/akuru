<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Models\FeeItem;
use App\Domains\Finance\Models\FeeStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFeeStructureAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?FeeStructure $structure = null): FeeStructure
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($yearId < 1) {
            throw ValidationException::withMessages(['academic_year_id' => 'Academic year is required.']);
        }

        $appliesTo = FeeStructureAppliesTo::from((string) ($data['applies_to'] ?? FeeStructureAppliesTo::SelectedClasses->value));
        $status = FeeStructureStatus::from((string) ($data['status'] ?? FeeStructureStatus::Draft->value));
        $classIds = $this->normalizeClassIds($data['class_ids'] ?? []);

        if ($appliesTo === FeeStructureAppliesTo::SelectedClasses && $classIds === []) {
            throw ValidationException::withMessages(['class_ids' => 'Select at least one class.']);
        }

        $items = $this->normalizeItems($data['items'] ?? []);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one fee item.']);
        }

        return DB::transaction(function () use ($structure, $name, $yearId, $appliesTo, $status, $classIds, $items) {
            $payload = [
                'academic_year_id' => $yearId,
                'name' => $name,
                'applies_to' => $appliesTo,
                'class_ids' => $appliesTo === FeeStructureAppliesTo::AllClasses ? null : $classIds,
                'status' => $status,
            ];

            if ($structure === null) {
                $structure = FeeStructure::query()->create($payload);
            } else {
                $structure->fill($payload);
                $structure->save();
            }

            $this->assertNoActiveOverlap($structure);

            $structure->items()->delete();
            foreach ($items as $item) {
                $structure->items()->create($item);
            }

            return $structure->fresh('items');
        });
    }

    /**
     * @return list<int>
     */
    private function normalizeClassIds(mixed $value): array
    {
        $ids = is_array($value) ? $value : [];

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $feeItemId = (int) ($row['fee_item_id'] ?? 0);
            if ($feeItemId < 1 || ! FeeItem::query()->whereKey($feeItemId)->exists()) {
                throw ValidationException::withMessages(['items' => 'Each line needs a valid fee item.']);
            }

            $amount = $row['amount'] ?? null;
            if ($amount === null || $amount === '' || (float) $amount < 0) {
                throw ValidationException::withMessages(['items' => 'Amount must be zero or more.']);
            }

            $dueDay = $row['due_day'] ?? null;
            $dueDay = $dueDay === '' || $dueDay === null ? null : (int) $dueDay;
            if ($dueDay !== null && ($dueDay < 1 || $dueDay > 31)) {
                throw ValidationException::withMessages(['due_day' => 'Due day must be between 1 and 31.']);
            }

            $feeItem = FeeItem::query()->find($feeItemId);
            $frequency = (string) ($row['frequency'] ?? $feeItem?->frequency?->value ?? FeeFrequency::OneTime->value);

            $items[] = [
                'fee_item_id' => $feeItemId,
                'amount' => $amount,
                'frequency' => FeeFrequency::from($frequency),
                'due_day' => $dueDay,
                'is_mandatory' => array_key_exists('is_mandatory', $row) ? (bool) $row['is_mandatory'] : true,
            ];
        }

        return $items;
    }

    private function assertNoActiveOverlap(FeeStructure $structure): void
    {
        if ($structure->status !== FeeStructureStatus::Active) {
            return;
        }

        $covered = $this->coveredClassIds($structure);
        $others = FeeStructure::query()
            ->where('academic_year_id', $structure->academic_year_id)
            ->where('status', FeeStructureStatus::Active->value)
            ->whereKeyNot($structure->id)
            ->get();

        foreach ($others as $other) {
            if ($this->coversSameScope($structure, $other, $covered)) {
                throw ValidationException::withMessages([
                    'status' => 'Only one active fee structure is allowed per class per year.',
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $covered
     */
    private function coversSameScope(FeeStructure $incoming, FeeStructure $other, array $covered): bool
    {
        if ($incoming->applies_to === FeeStructureAppliesTo::AllClasses
            || $other->applies_to === FeeStructureAppliesTo::AllClasses) {
            return true;
        }

        return array_intersect($covered, $this->coveredClassIds($other)) !== [];
    }

    /**
     * @return list<int>
     */
    private function coveredClassIds(FeeStructure $structure): array
    {
        if ($structure->applies_to === FeeStructureAppliesTo::AllClasses) {
            return app(ListClassesForYearAction::class)
                ->execute($structure->academic_year_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return array_values(array_map('intval', $structure->class_ids ?? []));
    }
}
