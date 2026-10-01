<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P7b: SMS offers from the Bookstore.
 *
 *  - `shop_sms_optins`: a customer who asked for offers by SMS (at checkout),
 *    and whether they have since said stop (by the link in every message, or
 *    by replying STOP). Nobody is sent an offer without a row here.
 *  - `shop_sms_campaigns`: what the office sent, to whom (everyone who opted
 *    in, or those of them who bought from one shop), how many messages, and
 *    what it cost — the monthly budget is read from these.
 *  - `shop_sms_campaign_recipients`: one row per phone, with the message as
 *    sent (each carries its own opt-out link) and whether it went.
 *
 * Commerce, so no `academic_year_id` (as every bookstore table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sms_optins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 32)->unique();
            $table->string('token', 16)->unique();
            $table->string('source', 20);
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamps();
            $table->index(['opted_out_at', 'opted_in_at']);
        });

        Schema::create('shop_sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20);
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->text('message');
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedSmallInteger('segments')->default(1);
            $table->decimal('rate', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('shop_sms_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_sms_campaign_id')->constrained('shop_sms_campaigns')->cascadeOnDelete();
            $table->foreignId('shop_sms_optin_id')->nullable()->constrained('shop_sms_optins')->nullOnDelete();
            $table->string('phone', 32);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_sms_campaign_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sms_campaign_recipients');
        Schema::dropIfExists('shop_sms_campaigns');
        Schema::dropIfExists('shop_sms_optins');
    }
};
