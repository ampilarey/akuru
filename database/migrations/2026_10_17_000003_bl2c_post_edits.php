<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL2c (STATUS §5sh, D3): an edit to a published post waits beside
 * the live text — `pending_title`, `pending_body` — and replaces it only when
 * the office approves, after the class teacher for a student's post. The live
 * columns change in the approving action and nowhere else. Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->string('pending_title')->nullable()->after('title');
            $table->longText('pending_body')->nullable()->after('body');
            $table->timestamp('pending_saved_at')->nullable()->after('submitted_at');
        });

        // BL2a left `review_stage = office` on a post the office had decided;
        // a decided post is with nobody.
        DB::table('library_items')->where('content_type', 'post')->where('status', '!=', 'submitted')->whereNotNull('review_stage')->update(['review_stage' => null]);
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn(['pending_title', 'pending_body', 'pending_saved_at']);
        });
    }
};
