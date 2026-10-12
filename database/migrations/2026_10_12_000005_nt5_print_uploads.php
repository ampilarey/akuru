<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAME_TAGS_PLAN §3.4, §5.5, §5.6 and §11 NT5 (STATUS §5rs): a job made
 * from the customer's own artwork.
 *
 *  - `upload_document_id` — the artwork, a private document on the job;
 *  - `upload_kind` — one tag's artwork repeated on the layout (`tag`), or a
 *    whole A4 sheet already laid out (`sheet`);
 *  - `upload_meta` — what was read from the file (pixels, type, pages,
 *    transparency, colour), from which the preflight is worked out again
 *    whenever the options change;
 *  - `declared_at` — the customer's *I have the right to print this* (risk
 *    R4), and `warnings_accepted_at` — the preflight's warnings read and
 *    accepted, both recorded when the proof is made.
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('upload_document_id')->nullable()->after('print_document_id');
            $table->string('upload_kind', 10)->nullable()->after('upload_document_id');
            $table->json('upload_meta')->nullable()->after('upload_kind');
            $table->timestamp('declared_at')->nullable()->after('upload_meta');
            $table->timestamp('warnings_accepted_at')->nullable()->after('declared_at');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['upload_document_id', 'upload_kind', 'upload_meta', 'declared_at', 'warnings_accepted_at']);
        });
    }
};
