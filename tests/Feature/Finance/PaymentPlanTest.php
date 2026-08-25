<?php

use App\Domains\Finance\Actions\AllocatePaymentAction;
use App\Domains\Finance\Actions\CreatePaymentPlanAction;
use App\Domains\Finance\Actions\MarkDefaultedPaymentPlansAction;
use App\Domains\Finance\Enums\InstallmentStatus;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Enums\PaymentPlanStatus;
use App\Domains\Finance\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeSchoolInvoice(int $createdBy, int $studentId, int $yearId, float $total = 1000): Invoice
{
    return Invoice::query()->create([
        'invoice_number' => 'INV-PLAN-'.$studentId.'-'.$total,
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
        'invoice_type' => InvoiceType::SchoolFees,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-03-01',
        'status' => InvoiceStatus::Sent,
        'total_amount' => $total,
        'paid_amount' => 0,
        'created_by' => $createdBy,
    ]);
}

it('allocates to the oldest installment, rejects overpayment, and defaults without locking school access', function () {
    expect(Schema::hasTable('payment_plans'))->toBeTrue()
        ->and(Schema::hasTable('payment_plan_installments'))->toBeTrue();

    $admin = actingPeopleAdmin(['finance.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent();
    $invoice = makeSchoolInvoice($admin->id, $student->id, $year->id, 1000);

    expect(fn () => app(CreatePaymentPlanAction::class)->execute([
        'invoice_id' => $invoice->id,
        'created_by' => $admin->id,
        'installments' => [
            ['amount' => 400, 'due_date' => '2026-01-15'],
            ['amount' => 400, 'due_date' => '2026-02-15'],
        ],
    ]))->toThrow(ValidationException::class);

    $plan = app(CreatePaymentPlanAction::class)->execute([
        'invoice_id' => $invoice->id,
        'created_by' => $admin->id,
        'installments' => [
            ['amount' => 400, 'due_date' => '2026-01-15'],
            ['amount' => 600, 'due_date' => '2026-02-15'],
        ],
    ]);

    expect($invoice->fresh()->payment_plan_id)->toBe($plan->id)
        ->and($plan->installments)->toHaveCount(2);

    app(AllocatePaymentAction::class)->execute($invoice, 250);
    $first = $plan->installments()->orderBy('sequence')->first();
    expect((float) $first->fresh()->paid_amount)->toBe(250.0)
        ->and($first->fresh()->status)->toBe(InstallmentStatus::Partial)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(250.0);

    app(AllocatePaymentAction::class)->execute($invoice->fresh(), 250);
    expect((float) $first->fresh()->paid_amount)->toBe(400.0)
        ->and($first->fresh()->status)->toBe(InstallmentStatus::Paid)
        ->and((float) $plan->installments()->orderBy('sequence')->get()[1]->paid_amount)->toBe(100.0);

    expect(fn () => app(AllocatePaymentAction::class)->execute($invoice->fresh(), 600))
        ->toThrow(ValidationException::class);

    app(AllocatePaymentAction::class)->execute($invoice->fresh(), 500);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($plan->fresh()->status)->toBe(PaymentPlanStatus::Completed);

    $other = makeSchoolInvoice($admin->id, makeStudent()->id, $year->id, 800);
    $latePlan = app(CreatePaymentPlanAction::class)->execute([
        'invoice_id' => $other->id,
        'created_by' => $admin->id,
        'installments' => [
            ['amount' => 800, 'due_date' => '2026-01-01'],
        ],
    ]);

    expect(app(MarkDefaultedPaymentPlansAction::class)->execute('2026-01-20'))->toBe(1)
        ->and($latePlan->fresh()->status)->toBe(PaymentPlanStatus::Defaulted)
        ->and($other->fresh()->status)->toBe(InvoiceStatus::Sent);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.payment-plans.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/PaymentPlans/Index')
            ->has('plans', 2)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.payment-plans.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain($invoice->invoice_number)->toContain('completed');
});
