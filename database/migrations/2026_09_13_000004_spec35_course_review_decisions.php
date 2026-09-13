<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §35 "Dean / Supervisor Dashboard" names eight abilities. Three of them
 * had nowhere to be recorded:
 *
 *   > Approve courses · **Reject courses** · **Request changes**
 *
 * and §34 "Course Creator Dashboard" names the other half of the same missing
 * thing:
 *
 *   > **View supervisor comments**
 *
 * The workflow itself works: `draft → in_review → published → archived`, with
 * `in_review → draft` as the way back. The catalog screen has the buttons —
 * "Submit review", "Publish", "Return draft", "Archive".
 *
 * **"Return draft" carries no reason at all.** A creator whose course is
 * bounced back is told nothing: no comment, no reviewer, no date, no
 * distinction between "reject" and "request changes". Three §35/§34 abilities
 * rest on a record that was never kept, and the supervisor's actual review —
 * the only part of the exchange with any content in it — was discarded the
 * moment the button was pressed.
 *
 * **Append-only** (rule 12's discipline, applied beyond money because the same
 * reasoning holds): a review decision is a thing that happened. A later
 * decision is a new row, never an edit of the old one, so the creator can see
 * what was asked of them and when. Nothing in the domain updates or deletes
 * these rows.
 *
 * Rule 10: a review decision happens in time, so it carries `academic_year_id`.
 * Nullable, because a course under review need not belong to a year yet — the
 * courses table has no year of its own.
 *
 * Purely additive (rule 9): a new table drops and renames nothing, and no
 * existing read changes until something writes to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_review_decisions')) {
            return;
        }

        Schema::create('course_review_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 32);
            $table->text('comment')->nullable();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->timestamps();

            // The creator's view is "the decisions on my course, newest first".
            $table->index(['course_id', 'created_at']);
            $table->index('academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_review_decisions');
    }
};
