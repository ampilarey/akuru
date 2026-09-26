<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B6: money to vendors (§5 "Money", §7 "Payouts", §8,
 * §9 "Money"). Additive only (rule 9); money is decimal(10,2) like the
 * wallet (§8 currency).
 *
 *  - `vendor_earnings`: one row per paid order — the goods, the discount
 *    and who funded it, the delivery fee (the vendor's, uncommissioned),
 *    the commission at the vendor's rate on goods (decision 5), the net.
 *    Refunds reverse it in proportion; it matures after the return window
 *    from delivery; a payout pays its balance (`net − paid_amount`), so a
 *    refund after a payout is clawed back from the next one.
 *  - `vendor_payouts`: the owner's request for the matured balance, the
 *    office's decision, the bank reference it was paid under.
 *  - `vendor_bank_details`: where a payout goes; one row per vendor,
 *    entered by the owner in the portal (never through the office, never
 *    in a kit file — FITRAH.md).
 *  - `vendor_commission_invoices` (audit finding 4): Akuru's monthly tax
 *    invoice to the vendor for its commission, so an accountant can book
 *    it; GST on it only when Akuru is registered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained('vendors')->cascadeOnDelete();
            $table->string('bank_name', 120);
            $table->string('account_name', 160);
            $table->string('account_number', 50);
            $table->string('currency', 3)->default('MVR');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('status', 20)->default('requested'); // requested / paid / rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reference', 120)->nullable(); // the bank transfer's reference
            $table->string('note', 500)->nullable();
            $table->json('bank_snapshot')->nullable(); // where it was sent, as it was then
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->decimal('gross', 10, 2); // goods at full price
            $table->decimal('discount', 10, 2)->default(0); // the vendor's share of the checkout's discount
            $table->string('discount_funding', 20)->nullable(); // akuru / vendor
            $table->decimal('delivery_fee', 10, 2)->default(0); // the vendor's, uncommissioned
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_base', 10, 2); // goods the commission applies to
            $table->decimal('commission', 10, 2); // current, after reversals
            $table->decimal('commission_tax_rate', 5, 2)->default(0); // GST on the commission, when Akuru is registered
            $table->decimal('commission_tax', 10, 2)->default(0); // current, after reversals
            $table->decimal('net', 10, 2); // current, after reversals
            $table->decimal('refunded', 10, 2)->default(0); // customer money gone back so far
            $table->decimal('paid_amount', 10, 2)->default(0); // what a payout has paid on this row
            $table->string('status', 20)->default('pending'); // pending / available / paid / reversed
            $table->timestamp('order_paid_at');
            $table->timestamp('available_at')->nullable(); // delivery + the return window
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('open_payout_id')->nullable()->constrained('vendor_payouts')->nullOnDelete();
            $table->foreignId('last_payout_id')->nullable()->constrained('vendor_payouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'order_paid_at']);
        });

        Schema::create('vendor_commission_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('sales', 10, 2); // commission base in the period
            $table->decimal('commission', 10, 2); // the invoice's net amount
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('issuer_name', 160);
            $table->string('issuer_tin', 40)->nullable();
            $table->string('vendor_legal_name', 160);
            $table->string('vendor_tin', 40)->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_commission_invoices');
        Schema::dropIfExists('vendor_earnings');
        Schema::dropIfExists('vendor_payouts');
        Schema::dropIfExists('vendor_bank_details');
    }
};
