<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LENDING_AND_USED_BOOKS_PLAN L2: the rest of lending. Reminder marks on a
 * loan (so each is sent once, overdue once a day), ratings of each other
 * after a return, and the office's notes when it pauses a lender or takes a
 * book down. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lending_loans', function (Blueprint $table) {
            $table->timestamp('reminded_before_at')->nullable()->after('returned_at');
            $table->timestamp('reminded_due_at')->nullable()->after('reminded_before_at');
            $table->date('last_overdue_reminder_on')->nullable()->after('reminded_due_at');
        });
        Schema::table('lenders', function (Blueprint $table) {
            $table->boolean('office_paused')->default(false)->after('status');
            $table->string('office_note', 500)->nullable()->after('office_paused');
        });
        Schema::table('lending_books', function (Blueprint $table) {
            $table->string('office_note', 500)->nullable()->after('status');
        });
        Schema::create('lending_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lending_loan_id')->constrained('lending_loans')->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained('lenders')->cascadeOnDelete();
            $table->unsignedBigInteger('by_user_id');
            $table->unsignedBigInteger('about_user_id');
            $table->string('about', 10); // lender / borrower — who is being rated
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['lending_loan_id', 'by_user_id']);
            $table->index(['about_user_id', 'about']);
            $table->index(['lender_id', 'about']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lending_ratings');
        Schema::table('lending_books', fn (Blueprint $table) => $table->dropColumn('office_note'));
        Schema::table('lenders', fn (Blueprint $table) => $table->dropColumn(['office_paused', 'office_note']));
        Schema::table('lending_loans', fn (Blueprint $table) => $table->dropColumn(['reminded_before_at', 'reminded_due_at', 'last_overdue_reminder_on']));
    }
};
