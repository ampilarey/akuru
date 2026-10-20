<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BACKLOG B1 (STATUS §5tg): text recognition for scanned Library PDFs.
 *
 *  - `library_items.ocr_media_file_id`: the PDF original whose pages text
 *    recognition made. While it equals `pdf_media_file_id` a save leaves the
 *    pages alone, so a writer editing the title does not throw away minutes
 *    of recognition and start it again.
 *  - `library_items.pages_recognised_at`: when recognition finished; null
 *    while it is still reading.
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->unsignedBigInteger('ocr_media_file_id')->nullable()->after('pdf_media_file_id');
            $table->timestamp('pages_recognised_at')->nullable()->after('ocr_media_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn(['ocr_media_file_id', 'pages_recognised_at']);
        });
    }
};
