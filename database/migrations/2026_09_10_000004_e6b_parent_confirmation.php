<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E6b — a pupil's answer waits for a guardian.
 *
 * EduPage's sign-up module makes this distinction because it matters: a child
 * ticking "yes, I am going on the trip" is not the same fact as their parent
 * agreeing to it, and a results table that conflates the two will send children
 * on coaches their families never approved.
 *
 * Additive only (rule 9): both columns are nullable and every existing form
 * keeps behaving exactly as it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('requires_parent_confirmation')->default(false)->after('is_anonymous');
        });

        Schema::table('form_responses', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('submitted_at');
            // Who confirmed, so an answer can never look confirmed with nobody
            // accountable for having confirmed it.
            $table->foreignId('confirmed_by_user_id')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by_user_id');
            $table->dropColumn('confirmed_at');
        });
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('requires_parent_confirmation');
        });
    }
};
