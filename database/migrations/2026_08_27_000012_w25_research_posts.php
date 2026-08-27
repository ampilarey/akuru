<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'authors')) {
                $table->json('authors')->nullable()->after('author_id');
            }
            if (! Schema::hasColumn('posts', 'pdf_document_id')) {
                $table->foreignId('pdf_document_id')->nullable()->after('authors')->constrained('media_files')->nullOnDelete();
            }
            if (! Schema::hasColumn('posts', 'abstract')) {
                $table->text('abstract')->nullable()->after('summary');
            }
            if (! Schema::hasColumn('posts', 'citation_note')) {
                $table->text('citation_note')->nullable()->after('abstract');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'pdf_document_id')) {
                $table->dropConstrainedForeignId('pdf_document_id');
            }
            foreach (['authors', 'abstract', 'citation_note'] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
