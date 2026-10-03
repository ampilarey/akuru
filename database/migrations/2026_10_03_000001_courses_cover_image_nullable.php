<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BACKLOG C16 slice N2 (STATUS §5nx): the website course's cover is an upload
 * now, and a course may be saved before it has one — the public pages already
 * read `@if($course->cover_image)`. Widening a NOT NULL column to nullable is
 * additive (rule 9); nothing is dropped or renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('cover_image')->nullable(false)->default('')->change();
        });
    }
};
