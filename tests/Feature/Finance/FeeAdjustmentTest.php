<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Finance\Actions\GenerateInvoicesAction;
use App\Domains\Finance\Actions\SaveFeeAdjustmentAction;
use App\Domains\Finance\Actions\SaveFeeStructureAction;
use App\Domains\Finance\Actions\SuggestSiblingFeeAdjustmentsAction;
use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('applies percent then fixed adjustments inside the validity window and suggests siblings', function () {
    expect(Schema::hasTable('fee_adjustments'))->toBeTrue();

    $admin = actingPeopleAdmin(['finance.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $item = makeCatalogFeeItem();
    $structure = app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $year->id,
        'name' => 'Fees',
        'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
        'class_ids' => [$class->id],
        'status' => FeeStructureStatus::Active->value,
        'items' => [[
            'fee_item_id' => $item->id,
            'amount' => 1000,
            'frequency' => FeeFrequency::Monthly->value,
            'is_mandatory' => true,
        ]],
    ]);

    $student = makeStudent(['first_name' => 'Aisha']);
    $sibling = makeStudent(['first_name' => 'Yusuf']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true, true, true);
    app(AttachGuardianAction::class)->execute($sibling, $guardian, 'father', true, true, true);

    app(SaveFeeAdjustmentAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'type' => FeeAdjustmentType::Scholarship->value,
        'basis' => FeeAdjustmentBasis::Percent->value,
        'value' => 10,
        'applies_to' => FeeAdjustmentAppliesTo::AllItems->value,
        'status' => FeeAdjustmentStatus::Approved->value,
        'approved_by' => $admin->id,
        'valid_from' => '2026-01-01',
        'valid_until' => '2026-12-31',
    ]);
    app(SaveFeeAdjustmentAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'type' => FeeAdjustmentType::HardshipWaiver->value,
        'basis' => FeeAdjustmentBasis::Fixed->value,
        'value' => 100,
        'applies_to' => FeeAdjustmentAppliesTo::AllItems->value,
        'status' => FeeAdjustmentStatus::Approved->value,
        'approved_by' => $admin->id,
        'valid_from' => '2026-01-01',
        'valid_until' => '2026-12-31',
    ]);
    app(SaveFeeAdjustmentAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'type' => FeeAdjustmentType::StaffChild->value,
        'basis' => FeeAdjustmentBasis::Percent->value,
        'value' => 50,
        'applies_to' => FeeAdjustmentAppliesTo::AllItems->value,
        'status' => FeeAdjustmentStatus::Approved->value,
        'approved_by' => $admin->id,
        'valid_from' => '2025-01-01',
        'valid_until' => '2025-12-31',
    ]);

    $invoices = app(GenerateInvoicesAction::class)->execute([
        'fee_structure_id' => $structure->id,
        'class_id' => $class->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'monthly_mode' => 'per_month',
        'created_by' => $admin->id,
    ]);

    $invoice = $invoices->first();
    expect($invoice->lines)->toHaveCount(3)
        ->and((float) $invoice->discount_amount)->toBe(200.0)
        ->and((float) $invoice->total_amount)->toBe(800.0)
        ->and($invoice->lines->pluck('notes')->filter()->implode(' '))->toContain('not a Commerce code');

    $suggestions = app(SuggestSiblingFeeAdjustmentsAction::class)->execute($student->id, $year->id);
    expect($suggestions->pluck('student_id'))->toContain($sibling->id)
        ->and($suggestions->first()['suggested_type'])->toBe('sibling_discount');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.adjustments.index', [
            'academic_year_id' => $year->id,
            'student_id' => $student->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Adjustments/Index')
            ->has('adjustments', 3)
            ->has('suggestions', 1)
        );
});
