<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC "Backups" (BK1, STATUS §5up): one row per backup run — when it ran,
 * what it made, where it went, or why it failed. The server's own record,
 * not school data, so it carries no academic year (rule 10 is for things
 * that happen in a school year). Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->nullable();
            $table->string('status', 16)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedBigInteger('database_bytes')->nullable();
            $table->unsignedBigInteger('files_bytes')->nullable();
            $table->unsignedInteger('tables')->nullable();
            $table->boolean('offsite')->default(false);
            $table->string('offsite_error', 500)->nullable();
            $table->unsignedSmallInteger('removed')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
