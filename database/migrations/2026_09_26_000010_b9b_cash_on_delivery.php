<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B9b: cash on delivery (decision 7: "cash on delivery
 * in B9"). Additive only (rule 9).
 *
 *  - `vendors.cod_enabled`, `vendors.cod_max`: a shop opts in, and may cap
 *    the order it will carry cash for. The office's switch is a setting.
 *  - `vendor_earnings.cash_collected`: the cash the shop took at the door
 *    on Akuru's behalf. The earning's `net` is what Akuru owes the shop
 *    after it — for a cash order, usually minus the commission, which the
 *    next payout settles. Checkout, order and payment-method values are
 *    strings already; the new ones (`cash_on_delivery`, `cash_due`) need
 *    no column change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('cod_enabled')->default(false)->after('notice_settings');
            $table->decimal('cod_max', 10, 2)->nullable()->after('cod_enabled');
        });

        Schema::table('vendor_earnings', function (Blueprint $table) {
            $table->decimal('cash_collected', 10, 2)->default(0)->after('net');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_earnings', fn (Blueprint $table) => $table->dropColumn('cash_collected'));
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn(['cod_enabled', 'cod_max']));
    }
};
