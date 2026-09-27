<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B14 (LIBRARY_PLAN §29, STATUS §5it): what people type into the shelf's
 * search box, so the office can see what is looked for and what is looked
 * for and not found. The term and the hit count only — a search is not a
 * reading record, and the rows are pruned with the reading events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100);
            $table->unsignedInteger('hits')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'term'], 'library_search_logs_when_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_search_logs');
    }
};
