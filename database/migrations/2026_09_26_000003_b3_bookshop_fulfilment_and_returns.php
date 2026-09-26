<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B3: fulfilment and returns. Additive only (rule 9).
 *
 *  - `orders` gains the fulfilment trail's timestamps, the carrier and
 *    tracking note, why and by whom it was cancelled, and the message
 *    thread its customer and shop talk on (Notifications owns the thread;
 *    the order keeps only its id, rule 3).
 *  - `vendors` gains its return window and conditions (decision 8: seven
 *    days is the floor, a vendor may offer longer). Holiday mode already
 *    has its three columns from B1a.
 *  - `order_returns`: a customer's request to send an item back, and the
 *    vendor's answer.
 *  - `order_refunds`: money going back for a cancellation or an accepted
 *    return. Wallet money goes back at once; card money waits for the
 *    office (plan audit finding 6). Finance's own refund row is linked
 *    when the money went through `RefundPaymentAction`.
 *
 * No `academic_year_id`: commerce, as every bookshop table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('processing_at')->nullable()->after('paid_at');
            $table->timestamp('ready_at')->nullable()->after('processing_at');
            $table->timestamp('dispatched_at')->nullable()->after('ready_at');
            $table->timestamp('delivered_at')->nullable()->after('dispatched_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_by');
            $table->string('carrier', 120)->nullable()->after('cancel_reason');
            $table->string('tracking_note', 500)->nullable()->after('carrier');
            $table->unsignedBigInteger('message_thread_id')->nullable()->after('tracking_note');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->unsignedSmallInteger('return_window_days')->nullable()->after('holiday_notice');
            $table->text('return_conditions')->nullable()->after('return_window_days');
        });

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason', 30);
            $table->string('note', 1000)->nullable();
            $table->string('status', 20)->default('requested');
            $table->decimal('refund_amount', 10, 2)->default(0);
            // The shop's fault (faulty, wrong, not as described): the
            // delivery fee goes back too, once per order.
            $table->boolean('refunds_delivery')->default(false);
            $table->boolean('restocked')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_return_id')->nullable()->constrained('order_returns')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            // What the customer paid with, which decides who can send it back.
            $table->string('paid_with', 20);
            $table->string('status', 20)->default('pending');
            // Where it went: the wallet, or back through the bank (recorded
            // by the office once done in BML's merchant portal).
            $table->string('destination', 20)->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_returns');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['return_window_days', 'return_conditions']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['processing_at', 'ready_at', 'dispatched_at', 'delivered_at', 'cancelled_at', 'cancel_reason', 'carrier', 'tracking_note', 'message_thread_id']);
        });
    }
};
