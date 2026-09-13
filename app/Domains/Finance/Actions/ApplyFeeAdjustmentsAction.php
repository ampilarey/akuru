<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Models\FeeAdjustment;
use App\Domains\Finance\Models\FeeItem;
use App\Domains\Finance\Models\Invoice;

/**
 * School-fee adjustments (sibling / scholarship / waiver) are applied here
 * at generation time — not Commerce discount codes (those live in L4).
 * Stacking order is documented: all matching percents (additive on original
 * matching subtotal), then fixed amounts, each as its own invoice line.
 */
class ApplyFeeAdjustmentsAction
{
    public function execute(Invoice $invoice): Invoice
    {
        $invoice->load('lines.feeItem');
        $asOf = $invoice->issue_date?->toDateString() ?? now('Indian/Maldives')->toDateString();

        $adjustments = FeeAdjustment::query()
            ->where('student_id', $invoice->student_id)
            ->where('academic_year_id', $invoice->academic_year_id)
            ->where('status', FeeAdjustmentStatus::Approved->value)
            ->get()
            ->filter(fn (FeeAdjustment $row) => $this->isValidOn($row, $asOf))
            ->sortBy(fn (FeeAdjustment $row) => $row->basis === FeeAdjustmentBasis::Percent ? 0 : 1)
            ->values();

        foreach ($adjustments as $adjustment) {
            // A discount comes off what is still owed, not off the original
            // bill. This ran for fixed adjustments only, so two approved
            // percent adjustments — a 60% scholarship and a 60% staff-child
            // rate, both perfectly ordinary — each took their cut of the full
            // gross and the invoice came to **minus 200**.
            //
            // `$already` is every discount on the invoice, not just the ones
            // this adjustment's item types would have matched, because the
            // adjustment lines carry no `fee_item_id` and cannot be attributed
            // back to a type. With mixed scopes that under-discounts rather
            // than over-discounts, which is the direction to err in, and it is
            // the trade the fixed branch was already making.
            $already = $invoice->lines->sum(fn ($line) => (float) $line->discount_amount);
            $base = max(0, $this->matchingSubtotal($invoice, $adjustment) - $already);

            if ($base <= 0) {
                continue;
            }

            $amount = $adjustment->basis === FeeAdjustmentBasis::Percent
                ? round($base * ((float) $adjustment->value / 100), 2)
                : min((float) $adjustment->value, $base);

            if ($amount <= 0) {
                continue;
            }

            $invoice->lines()->create([
                'fee_item_id' => null,
                'description' => $this->label($adjustment),
                'quantity' => 1,
                'unit_price' => 0,
                'line_total' => 0,
                'discount_percentage' => $adjustment->basis === FeeAdjustmentBasis::Percent ? $adjustment->value : 0,
                'discount_amount' => $amount,
                'notes' => 'school-fee adjustment (not a Commerce code)',
            ]);
            $invoice->unsetRelation('lines');
            $invoice->load('lines.feeItem');
        }

        return app(RecalculateInvoiceTotalsAction::class)->execute($invoice);
    }

    private function isValidOn(FeeAdjustment $adjustment, string $asOf): bool
    {
        if ($adjustment->valid_from && $adjustment->valid_from->toDateString() > $asOf) {
            return false;
        }

        if ($adjustment->valid_until && $adjustment->valid_until->toDateString() < $asOf) {
            return false;
        }

        return true;
    }

    private function matchingSubtotal(Invoice $invoice, FeeAdjustment $adjustment): float
    {
        $types = array_map('strval', $adjustment->item_types ?? []);

        return $invoice->lines
            ->filter(function ($line) use ($adjustment, $types) {
                if ((float) $line->discount_amount > 0 && (float) $line->unit_price == 0.0) {
                    return false;
                }
                if ($adjustment->applies_to === FeeAdjustmentAppliesTo::AllItems) {
                    return $line->fee_item_id !== null;
                }
                $type = $line->feeItem instanceof FeeItem ? $line->feeItem->type?->value : null;

                return $type !== null && in_array($type, $types, true);
            })
            ->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price);
    }

    private function label(FeeAdjustment $adjustment): string
    {
        $type = str_replace('_', ' ', $adjustment->type?->value ?? 'adjustment');
        $value = $adjustment->basis === FeeAdjustmentBasis::Percent
            ? rtrim(rtrim((string) $adjustment->value, '0'), '.').'%'
            : $adjustment->value.' MVR';

        return ucfirst($type).' ('.$value.')';
    }
}
