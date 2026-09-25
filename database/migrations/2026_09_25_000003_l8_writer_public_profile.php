<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * L8 — the public author page (LIBRARY_PLAN §8.7).
 *
 * A writer's profile so far served only the writer's own portal and the
 * office. For a reader to reach "everything this author published" the
 * profile needs an address (`slug`) and a face (`photo_media_file_id`,
 * public media — a portrait is published on purpose, unlike the PDF
 * original). Additive; every existing writer gets a slug from their
 * display name so the page works for them the moment this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('display_name');
            $table->foreignId('photo_media_file_id')->nullable()->after('expertise')->constrained('media_files')->nullOnDelete();
        });

        $taken = [];
        foreach (DB::table('writer_profiles')->orderBy('id')->get(['id', 'display_name']) as $row) {
            $base = Str::slug((string) $row->display_name) ?: 'writer';
            $slug = $base;
            for ($n = 2; in_array($slug, $taken, true); $n++) {
                $slug = $base.'-'.$n;
            }
            $taken[] = $slug;
            DB::table('writer_profiles')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_media_file_id');
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
