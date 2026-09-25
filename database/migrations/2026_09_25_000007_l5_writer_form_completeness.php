<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBRARY_PLAN §11.3 / §11.5: what a writer declares and describes when
 * they upload. The form had title, type, access, price, abstract, body
 * and a PDF; the plan asks for a table of contents, declarations
 * (copyright, AI use; for research originality, conflict of interest,
 * ethics), affiliation, field and a suggested reviewer. Additive.
 *
 * `declarations` is a JSON map of declaration name → true, because the set
 * differs by content type and will grow; `declared_at` is when the
 * copyright declaration was first made, which is the one that gates
 * submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->text('toc')->nullable()->after('body');
            $table->string('affiliation')->nullable()->after('citations');
            $table->string('research_field')->nullable()->after('affiliation');
            $table->string('suggested_reviewer')->nullable()->after('research_field');
            $table->json('declarations')->nullable()->after('suggested_reviewer');
            $table->timestamp('declared_at')->nullable()->after('declarations');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn(['toc', 'affiliation', 'research_field', 'suggested_reviewer', 'declarations', 'declared_at']);
        });
    }
};
