<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('user_contacts')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('status', [
                'started',
                'otp_sent',
                'verified',
                'selecting_students',
                'enrolling',
                'payment_pending',
                'completed',
                'failed',
            ])->default('started');
            $table->json('payload')->nullable()->comment('course_ids, student data, term_id, etc.');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });

        // Add unique constraint to user_contacts(type, value) if not already present.
        // Safe to call even if it already exists – catches duplicate key race conditions.
        try {
            Schema::table('user_contacts', function (Blueprint $table) {
                $table->unique(['type', 'value'], 'user_contacts_type_value_unique');
            });
        } catch (\Throwable) {
            // Constraint already exists – safe to ignore
        }

        // Ensure payments.merchant_reference is unique
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique('merchant_reference', 'payments_merchant_reference_unique');
            });
        } catch (\Throwable) {
            // Already unique – safe to ignore
        }

        // Add amount_laar to payments if missing
        if (! Schema::hasColumn('payments', 'amount_laar')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('amount_laar')->default(0)->after('amount')
                      ->comment('Amount in laari (MVR cents). 100 laari = 1 MVR.');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_flows');

        if (Schema::hasColumn('payments', 'amount_laar')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('amount_laar');
            });
        }
    }
};
