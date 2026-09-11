<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E15 — lost and found.
 *
 * Staff log what turns up; families browse it and recognise their child's
 * water bottle without a phone call to the office. The whole value is that
 * the list is *visible*, so the family-facing read is the point of the slice
 * rather than an afterthought.
 *
 * Carries `academic_year_id` (rule 10): an item found is something that
 * happens on a date, and "what was handed in last year" must not silently
 * join this year's list. The year is stamped at creation from the active
 * year, not chosen — nobody logging a lost jumper should have to think about
 * which school year it is.
 *
 * The photo is a private media id, not a path (rule 4, and the same choice
 * E13c made): a picture of a child's belongings is not a public asset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('found_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('found_at');

            // Who is physically holding it — "ask at the office" is useless when
            // the office is three rooms.
            $table->string('held_at')->nullable();

            $table->string('status', 20)->default('listed');

            // Returned items stay on the record rather than vanishing: somebody
            // has to be able to answer "did anyone collect it?".
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('returned_to')->nullable();

            $table->foreignId('photo_media_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->timestamps();

            $table->index(['academic_year_id', 'status']);
            $table->index('found_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('found_items');
    }
};
