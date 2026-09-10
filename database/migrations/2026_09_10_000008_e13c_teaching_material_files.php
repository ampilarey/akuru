<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E13c — a material can carry files.
 *
 * The last of E13's v1 scope: "title, body, attachments, subject, tags". A
 * worksheet a teacher describes is not a worksheet a pupil can print.
 *
 * `media_file_id` is an **opaque handle**, not a relation Academics resolves
 * itself (rule 3). The display metadata is copied here at upload time from what
 * Media's own action returns, so listing a material never reads Media's table;
 * the bytes come back only through `ReadPrivateMediaAction`. Media keeps
 * ownership of storage, processing and lifecycle.
 *
 * No `academic_year_id` for the same reason as E13a: a file on a material is
 * standing content, not something that happens in time (rule 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_material_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_material_id')->constrained()->cascadeOnDelete();
            // Deliberately not a foreign key into Media's table: this is a
            // handle Academics passes back to Media, and a cascade here would
            // make one domain's cleanup silently rewrite another's rows.
            $table->unsignedBigInteger('media_file_id');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teaching_material_id', 'media_file_id'], 'material_file_unique');
            $table->index('media_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_material_files');
    }
};
