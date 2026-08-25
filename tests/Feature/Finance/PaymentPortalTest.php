<?php

use App\Domains\Finance\Actions\ListCollectionsAction;
use App\Domains\Finance\Actions\ListReconciliationAction;
use App\Domains\Finance\Actions\RecordInvoiceReceiptAction;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeFeeInvoice(int $createdBy, int $studentId, int $yearId, float $total = 1000): Invoice
{
    return Invoice::query()->create([
        'invoice_number' => 'INV-PAY-'.$studentId.'-'.uniqid(),
        'student_id' => $studentId,
        'academic_year_id' => $yearId,
        'invoice_type' => InvoiceType::SchoolFees,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'status' => InvoiceStatus::Sent,
        'total_amount' => $total,
        'paid_amount' => 0,
        'meta' => ['class_id' => 9],
        'created_by' => $createdBy,
    ]);
}

it('records webhook receipts once, gates manual entry, and scopes the parent portal', function () {
    $admin = actingPeopleAdmin(['finance.manage', 'finance.record-manual-payment']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent();
    $invoice = makeFeeInvoice($admin->id, $student->id, $year->id, 1000);

    $payment = Payment::query()->create([
        'user_id' => $admin->id,
        'unified_student_id' => $student->id,
        'amount' => 400,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'paid_at' => now(),
        'confirmed_at' => now(),
    ]);

    $first = app(RecordInvoiceReceiptAction::class)->fromConfirmedPayment($payment);
    $second = app(RecordInvoiceReceiptAction::class)->fromConfirmedPayment($payment);

    expect($first->id)->toBe($second->id)
        ->and(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(400.0)
        ->and($first->method)->toBe(ReceiptMethod::Bml)
        ->and($first->document_id)->not->toBeNull();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.receipts.show', $first))
        ->assertOk()
        ->assertSee($first->receipt_number, false);

    $stranger = User::factory()->create();
    $this->withoutLocalizationMiddleware()
        ->actingAs($stranger)
        ->post(route('finance.receipts.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'method' => 'cash',
        ])
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('finance.receipts.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'method' => 'cash',
        ])
        ->assertRedirect();

    expect((float) $invoice->fresh()->paid_amount)->toBe(500.0)
        ->and(Receipt::query()->where('invoice_id', $invoice->id)->count())->toBe(2);

    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true, true, true);
    $other = makeStudent(['first_name' => 'Other']);
    makeFeeInvoice($admin->id, $other->id, $year->id, 200);

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardian->user)
        ->get(route('portal.invoices', ['student_id' => $student->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Invoices')
            ->has('invoices', 1)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardian->user)
        ->get(route('portal.invoices', ['student_id' => $other->id]))
        ->assertForbidden();

    $collections = app(ListCollectionsAction::class)->execute($year->id);
    expect($collections->firstWhere('month', '2026-01')['collected'])->toBe('500.00');

    $recon = app(ListReconciliationAction::class)->execute();
    expect($recon['daily']->first()['method'])->toBeIn(['bml', 'cash'])
        ->and($recon['rows']->count())->toBe(2);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.reconciliation.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain($first->receipt_number);
});
