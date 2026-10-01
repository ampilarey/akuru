<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LENDING_AND_USED_BOOKS_PLAN L1 (STATUS §5ms): book lending between
 * Akuru's users — no money through Akuru (decision D4).
 *
 *  - `lenders`: one per user. A profile, not a dated record (rule 10's
 *    "not year-scoped", baselined).
 *  - `lending_books`: what a lender offers. A catalogue entry (baselined).
 *  - `lending_loans`: a request, its decision, the handover and the return.
 *    Something that happens in time, so it carries `academic_year_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('island', 120)->nullable();
            $table->string('about', 1000)->nullable();
            // Lend only to borrowers whose ID card the office has checked.
            $table->boolean('id_required')->default(false);
            $table->string('status', 20)->default('active'); // active / paused
            $table->timestamps();
        });

        Schema::create('lending_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained('lenders')->cascadeOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('language', 40)->nullable();
            $table->string('condition', 20)->default('good'); // like_new / good / fair / worn
            $table->string('description', 2000)->nullable();
            $table->string('grade', 40)->nullable();
            $table->string('subject', 80)->nullable();
            $table->unsignedSmallInteger('max_days')->default(14);
            // Words, not money (D4): "MVR 50, back when the book is".
            $table->string('deposit', 120)->nullable();
            $table->unsignedBigInteger('photo_media_id')->nullable();
            $table->string('status', 20)->default('available'); // available / on_loan / paused / removed
            $table->timestamps();

            $table->index(['status', 'lender_id']);
        });

        Schema::create('lending_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lending_book_id')->constrained('lending_books')->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained('lenders')->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('status', 20)->default('requested'); // requested / accepted / declined / cancelled / out / returned
            $table->string('message', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('handed_at')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['borrower_user_id', 'status']);
            $table->index(['lender_id', 'status']);
            $table->index(['status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lending_loans');
        Schema::dropIfExists('lending_books');
        Schema::dropIfExists('lenders');
    }
};
