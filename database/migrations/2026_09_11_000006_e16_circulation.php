<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E16 — physical circulation.
 *
 * **Not the L-track Library**, which is a digital reader and bookstore. The
 * plan is explicit: *"Name it distinctly (Circulation) so it never gets
 * confused with the L-track Library in code or nav."* Hence its own domain,
 * its own tables, and nav that says Circulation.
 *
 * The shape the plan asks for, and why each part earns its place:
 *
 *  - `book_titles` — the work. *Fathuruveri* is one title however many copies
 *    the school owns.
 *  - `book_copies` — the object on the shelf, each with an **accession
 *    number**, which is what a label carries and a scanner reads. Loans attach
 *    to a copy, never to a title: "who has our second copy" is the question a
 *    librarian actually asks.
 *  - `loans` — one copy, one borrower, out/due/returned.
 *
 * Borrowers are students or staff, held as **two nullable columns rather than
 * a morph**. There is no type column to disagree with the id, both keep real
 * foreign keys, and rule 11's single student record (People) still holds. A
 * check constraint would be the belt; `LendCopyAction` is the braces, and it
 * is the only writer.
 *
 * Carries `academic_year_id` on `loans` (rule 10) — issuing textbooks is the
 * canonical start-of-term event, and "what did we lend last year" must not
 * silently join this year's list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_titles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('classification', 40)->nullable();
            $table->string('language', 8)->default('en');
            $table->integer('loan_days')->default(14);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('title');
            $table->index('isbn');
        });

        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_title_id')->constrained('book_titles')->cascadeOnDelete();

            // What the label carries and the scanner reads. Unique across the
            // whole collection, which is the point of an accession number.
            $table->string('accession_number', 32)->unique();

            $table->string('status', 16)->default('available');
            $table->string('shelf', 40)->nullable();
            $table->timestamps();

            $table->index(['book_title_id', 'status']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('book_copy_id')->constrained('book_copies')->cascadeOnDelete();

            // Exactly one of these is set. See the class comment.
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->date('out_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();

            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 16)->default('out');
            $table->string('note')->nullable();
            $table->timestamps();

            // The three reads: this copy's history, this borrower's shelf, and
            // what is overdue today.
            $table->index(['book_copy_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['status', 'due_on']);
            $table->index(['academic_year_id', 'out_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('book_titles');
    }
};
