<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P4: the office approves a listing before it goes on
 * sale, and again when what the product *is* changes (decision D4). The new
 * status `pending_review` fits the existing string column. Products already on
 * sale are left as they are; a shop the office marks trusted skips the queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->text('review_note')->nullable()->after('submitted_at');
            $table->json('review_changes')->nullable()->after('review_note');
            $table->foreignId('reviewed_by')->nullable()->after('review_changes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('trusted')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('trusted');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['submitted_at', 'review_note', 'review_changes', 'reviewed_at']);
        });
    }
};
