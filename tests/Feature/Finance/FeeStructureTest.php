<?php

use App\Domains\Finance\Actions\CopyFeeStructuresFromLastYearAction;
use App\Domains\Finance\Actions\SaveFeeItemAction;
use App\Domains\Finance\Actions\SaveFeeStructureAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Models\FeeStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeTuitionItem(): \App\Domains\Finance\Models\FeeItem
{
    return app(SaveFeeItemAction::class)->execute([
        'name' => 'Tuition',
        'default_amount' => 1500,
        'type' => FeeItemType::Tuition->value,
        'frequency' => FeeFrequency::Monthly->value,
    ]);
}

it('enforces one active fee structure per class per year and copies last year as drafts', function () {
    expect(Schema::hasTable('fee_structures'))->toBeTrue()
        ->and(Schema::hasColumns('fee_structures', ['academic_year_id', 'name', 'applies_to', 'class_ids', 'status']))->toBeTrue()
        ->and(Schema::hasColumns('fee_structure_items', ['fee_structure_id', 'fee_item_id', 'amount', 'frequency', 'due_day', 'is_mandatory']))->toBeTrue();

    $admin = actingPeopleAdmin(['finance.manage']);
    $lastYear = makeYear([
        'name' => '2025-2026',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);
    $year = makeYear([
        'name' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
        'status' => 'active',
    ]);
    $lastClass = makeClass($lastYear);
    $classA = makeClass($year, 'Grade 1', 'A');
    $classB = makeClass($year, 'Grade 2', 'A');
    $item = makeTuitionItem();

    $active = app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $year->id,
        'name' => 'Primary A',
        'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
        'class_ids' => [$classA->id],
        'status' => FeeStructureStatus::Active->value,
        'items' => [[
            'fee_item_id' => $item->id,
            'amount' => 1600,
            'frequency' => FeeFrequency::Monthly->value,
            'due_day' => 5,
            'is_mandatory' => true,
        ]],
    ]);

    expect($active->items)->toHaveCount(1)
        ->and($active->items->first()->amount)->toBe('1600.00');

    expect(fn () => app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $year->id,
        'name' => 'Overlap A',
        'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
        'class_ids' => [$classA->id],
        'status' => FeeStructureStatus::Active->value,
        'items' => [[
            'fee_item_id' => $item->id,
            'amount' => 100,
            'frequency' => FeeFrequency::Monthly->value,
        ]],
    ]))->toThrow(ValidationException::class);

    $otherClass = app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $year->id,
        'name' => 'Primary B',
        'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
        'class_ids' => [$classB->id],
        'status' => FeeStructureStatus::Active->value,
        'items' => [[
            'fee_item_id' => $item->id,
            'amount' => 1400,
            'frequency' => FeeFrequency::Annual->value,
            'is_mandatory' => false,
        ]],
    ]);
    expect($otherClass->status)->toBe(FeeStructureStatus::Active)
        ->and($otherClass->items->first()->is_mandatory)->toBeFalse();

    expect(fn () => app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $year->id,
        'name' => 'Everyone',
        'applies_to' => FeeStructureAppliesTo::AllClasses->value,
        'status' => FeeStructureStatus::Active->value,
        'items' => [[
            'fee_item_id' => $item->id,
            'amount' => 100,
            'frequency' => FeeFrequency::Annual->value,
        ]],
    ]))->toThrow(ValidationException::class);

    app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $lastYear->id,
        'name' => 'Last year primary',
        'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
        'class_ids' => [$lastClass->id],
        'status' => FeeStructureStatus::Active->value,
        'items' => [[
            'fee_item_id' => $item->id,
            'amount' => 1200,
            'frequency' => FeeFrequency::Monthly->value,
            'due_day' => 10,
            'is_mandatory' => true,
        ]],
    ]);

    $copies = app(CopyFeeStructuresFromLastYearAction::class)->execute($year->id);
    $copy = $copies->firstWhere('name', 'Last year primary');

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(FeeStructureStatus::Draft)
        ->and($copy->academic_year_id)->toBe($year->id)
        ->and($copy->class_ids)->toBe([$classA->id])
        ->and($copy->items->first()->amount)->toBe('1200.00');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.fee-structures.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/FeeStructures/Index')
            ->has('structures', 3)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.fee-structures.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Primary A')->toContain('active');

    expect(FeeStructure::query()->where('academic_year_id', $year->id)->count())->toBe(3);
});
