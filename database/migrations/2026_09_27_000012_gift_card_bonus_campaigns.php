<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B4b (LIBRARY_PLAN §18 "gift card bonus buy-500-get-50", STATUS §5iy): a
 * campaign may put a bonus on gift cards bought while it runs — the buyer
 * pays the full amount and the card is issued for more. Not a discount
 * (rule 12 stands: nothing reduces what a gift card costs); added stored
 * value the Institute funds, recorded on the order and on the card's own
 * ledger. `minimum_amount` is the "buy 500" half. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_campaigns', function (Blueprint $table) {
            $table->decimal('minimum_amount', 10, 2)->nullable()->after('max_discount_amount');
        });
        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->decimal('bonus_amount', 10, 2)->default(0)->after('amount');
            $table->foreignId('promotion_campaign_id')->nullable()->after('bonus_amount')->constrained('promotion_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_campaign_id');
            $table->dropColumn('bonus_amount');
        });
        Schema::table('promotion_campaigns', function (Blueprint $table) {
            $table->dropColumn('minimum_amount');
        });
    }
};
