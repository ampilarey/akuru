<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Academics\Actions\ResolvePreviousAcademicYearAction;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Models\FeeStructure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CopyFeeStructuresFromLastYearAction
{
    /**
     * @return Collection<int, FeeStructure>
     */
    public function execute(int $targetYearId): Collection
    {
        $previous = app(ResolvePreviousAcademicYearAction::class)->execute($targetYearId);
        if ($previous === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'No previous academic year to copy from.',
            ]);
        }

        $sources = FeeStructure::query()
            ->with('items')
            ->where('academic_year_id', $previous['id'])
            ->orderBy('name')
            ->get();

        if ($sources->isEmpty()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Last year has no fee structures to copy.',
            ]);
        }

        $targetClassMap = $this->classKeyMap($targetYearId);
        $sourceClassKeys = $this->classIdToKeyMap((int) $previous['id']);

        return DB::transaction(function () use ($sources, $targetYearId, $targetClassMap, $sourceClassKeys) {
            $copies = collect();
            foreach ($sources as $source) {
                $classIds = null;
                if ($source->applies_to === FeeStructureAppliesTo::SelectedClasses) {
                    $classIds = [];
                    foreach ($source->class_ids ?? [] as $oldId) {
                        $key = $sourceClassKeys[(int) $oldId] ?? null;
                        if ($key !== null && isset($targetClassMap[$key])) {
                            $classIds[] = $targetClassMap[$key];
                        }
                    }
                    $classIds = array_values(array_unique($classIds));
                }

                $copy = FeeStructure::query()->create([
                    'academic_year_id' => $targetYearId,
                    'name' => $source->name,
                    'applies_to' => $source->applies_to,
                    'class_ids' => $classIds,
                    'status' => FeeStructureStatus::Draft,
                ]);

                foreach ($source->items as $item) {
                    $copy->items()->create([
                        'fee_item_id' => $item->fee_item_id,
                        'amount' => $item->amount,
                        'frequency' => $item->frequency,
                        'due_day' => $item->due_day,
                        'is_mandatory' => $item->is_mandatory,
                    ]);
                }

                $copies->push($copy->fresh('items'));
            }

            return $copies;
        });
    }

    /**
     * @return array<string, int>
     */
    private function classKeyMap(int $yearId): array
    {
        $map = [];
        foreach (app(ListClassesForYearAction::class)->execute($yearId) as $class) {
            $map[$this->classKey((string) $class['name'], (string) $class['section'])] = (int) $class['id'];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function classIdToKeyMap(int $yearId): array
    {
        $map = [];
        foreach (app(ListClassesForYearAction::class)->execute($yearId) as $class) {
            $map[(int) $class['id']] = $this->classKey((string) $class['name'], (string) $class['section']);
        }

        return $map;
    }

    private function classKey(string $name, string $section): string
    {
        return mb_strtolower(trim($name)).'|'.mb_strtolower(trim($section));
    }
}
