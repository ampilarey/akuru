<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BACKLOG C16 slice N5 (STATUS §5oa; OWNER_ACTIONS 14, decided 2026-10-03:
 * build the resume link). `registration_flows` had two readers and no
 * writer; now a flow is written when a family asks for a link to finish
 * later, and the link is a single-use token (its hash here), sent once,
 * consumed once. Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_flows', function (Blueprint $table) {
            $table->string('resume_token_hash', 64)->nullable()->after('payload');
            $table->timestamp('resume_sent_at')->nullable()->after('resume_token_hash');
            $table->timestamp('resumed_at')->nullable()->after('resume_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('registration_flows', function (Blueprint $table) {
            $table->dropColumn(['resume_token_hash', 'resume_sent_at', 'resumed_at']);
        });
    }
};
