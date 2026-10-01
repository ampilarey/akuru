<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P7c: what the office knows about a Bookstore
 * customer beyond their orders — tags (school, wholesale, VIP, …) and
 * notes, each with an optional follow-up date that is ticked off when done.
 * The person is the account (`users`); this is only the office's view of
 * them. Commerce, so no `academic_year_id` (as every bookstore table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('tags')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shop_customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->date('follow_up_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['done_at', 'follow_up_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_customer_notes');
        Schema::dropIfExists('shop_customer_profiles');
    }
};
