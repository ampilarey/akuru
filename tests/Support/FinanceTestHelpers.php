<?php

use App\Domains\Finance\Actions\SaveFeeItemAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Models\FeeItem;
use App\Domains\Finance\Models\Invoice;

function makeCatalogFeeItem(array $overrides = []): FeeItem
{
    return app(SaveFeeItemAction::class)->execute(array_merge([
        'name' => 'Tuition',
        'default_amount' => 1500,
        'type' => FeeItemType::Tuition->value,
        'frequency' => FeeFrequency::Monthly->value,
    ], $overrides));
}

/**
 * A sent school-fee invoice with nothing paid, for plan and allocation tests.
 * Lived inside `PaymentPlanTest` until `AllocationLockTest` needed it too.
 */
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
