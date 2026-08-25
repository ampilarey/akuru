<?php

use App\Domains\Finance\Actions\SaveFeeItemAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\FeeItem;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('scopes invoices to the year backbone and keeps fee-item grades during the transition', function () {
    expect(Schema::hasColumns('invoices', [
        'academic_year_id',
        'term_id',
        'invoice_type',
        'payment_plan_id',
        'student_id',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('fee_items', ['name_arabic', 'name_dhivehi', 'applicable_grades']))->toBeTrue()
        ->and(Schema::hasTable('receipts'))->toBeTrue()
        ->and(Schema::hasColumns('receipts', [
            'invoice_id',
            'payment_id',
            'receipt_number',
            'amount',
            'method',
            'received_by',
            'received_at',
            'document_id',
        ]))->toBeTrue();

    expect(Permission::query()->where('name', 'finance.record-manual-payment')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'finance.manage')->exists())->toBeTrue();

    $admin = actingPeopleAdmin(['finance.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $student = makeStudent();

    $item = app(SaveFeeItemAction::class)->execute([
        'name' => 'Tuition',
        'name_dhivehi' => 'ފީ',
        'default_amount' => 1500,
        'type' => FeeItemType::Tuition->value,
        'frequency' => FeeFrequency::Monthly->value,
        'applicable_grades' => ['Grade 1'],
    ]);

    expect($item->name_dhivehi)->toBe('ފީ')
        ->and($item->applicable_grades)->toBe(['Grade 1']);

    expect(fn () => app(SaveFeeItemAction::class)->execute([
        'name' => 'Bad',
        'default_amount' => -1,
        'type' => FeeItemType::Other->value,
        'frequency' => FeeFrequency::OneTime->value,
    ]))->toThrow(ValidationException::class);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-S41-1',
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'invoice_type' => InvoiceType::SchoolFees,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-15',
        'total_amount' => 1500,
        'created_by' => $admin->id,
    ]);

    $receipt = Receipt::query()->create([
        'invoice_id' => $invoice->id,
        'receipt_number' => 'RCPT-S41-1',
        'amount' => 500,
        'method' => ReceiptMethod::Cash,
        'received_by' => $admin->id,
        'received_at' => now(),
    ]);

    expect($invoice->fresh()->student_id)->toBe($student->id)
        ->and($invoice->invoice_type)->toBe(InvoiceType::SchoolFees)
        ->and($receipt->method)->toBe(ReceiptMethod::Cash);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.fee-items.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/FeeItems/Index')
            ->has('items', 1)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.fee-items.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Tuition')->toContain('1500.00');

    expect(FeeItem::query()->count())->toBe(1);
});
