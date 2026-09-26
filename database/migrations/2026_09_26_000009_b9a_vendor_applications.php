<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B9 (on request), part a: public vendor onboarding —
 * "apply → approve, like writers" (§3). Additive only (rule 9).
 *
 * A signed-in person applies to open a shop; the office approves (the shop
 * is created with the applicant as its owner, through the same Action the
 * office's own invitation uses) or declines with a note. The Vendor
 * Agreement is accepted on the application, so the new owner is not asked
 * again. No `academic_year_id`: commerce, as every bookshop table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('shop_name', 120);
            $table->string('legal_name', 255)->nullable();
            $table->string('tin', 40)->nullable();
            $table->string('contact_email', 255);
            $table->string('contact_phone', 40);
            $table->string('island', 120);
            $table->text('what_they_sell');
            $table->string('link', 255)->nullable();
            $table->timestamp('agreement_accepted_at');
            $table->string('status', 20)->default('pending'); // pending / approved / declined
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_applications');
    }
};
