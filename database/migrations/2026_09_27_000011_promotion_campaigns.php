<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B4 (LIBRARY_PLAN §18, §35.10, STATUS §5ix): promotion campaigns — a
 * scheduled discount that applies by itself to what it covers, with a
 * name a reader sees. Platform-wide Commerce data (rule 11: one discount
 * system), targeted with plain strings (`all`, `library_item`,
 * `library_category`, `writer_profile` — morph-map aliases, ADR-005) so
 * Commerce never imports another domain's models.
 *
 * A campaign's use is a `discount_redemptions` row like a code's, so the
 * writer-earnings funding logic, the webhook confirmation and the refund
 * release all keep working unchanged; `discount_code_id` becomes nullable
 * and `promotion_campaign_id` sits beside it — exactly one is set.
 * Additive apart from that widening, which has no live rows to widen
 * (ADR-021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('discount_type', 20); // percentage / fixed
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->string('funding_source', 20)->default('akuru'); // shared / akuru / writer
            $table->string('status', 20)->default('active'); // active / ended
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at'], 'promotion_campaigns_window_idx');
        });

        Schema::create('promotion_campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_campaign_id')->constrained('promotion_campaigns')->cascadeOnDelete();
            $table->string('target_type', 30); // all / library_item / library_category / writer_profile
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id'], 'promotion_campaign_targets_target_idx');
        });

        Schema::table('discount_redemptions', function (Blueprint $table) {
            $table->foreignId('discount_code_id')->nullable()->change();
            $table->foreignId('promotion_campaign_id')->nullable()->after('discount_code_id')->constrained('promotion_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discount_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_campaign_id');
        });
        Schema::dropIfExists('promotion_campaign_targets');
        Schema::dropIfExists('promotion_campaigns');
    }
};
