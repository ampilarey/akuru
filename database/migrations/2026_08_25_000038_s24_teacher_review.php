<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2.4 — teacher review fields on activity and assessment attempts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_attempts', function (Blueprint $table) {
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->text('feedback')->nullable();
            $table->json('item_scores')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_attempts', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'reviewed_by', 'reviewed_at']);
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'item_scores', 'reviewed_by', 'reviewed_at']);
        });
    }
};
