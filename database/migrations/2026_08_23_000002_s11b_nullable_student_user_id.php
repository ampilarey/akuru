<?php

use App\Support\Schema\ForeignKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1.1b — additive schema so a unified student may have no user account
 * (small child; guardian login). Unique legacy key enforces RS → student 1:1.
 *
 * Central database/migrations/ — domain folders are still unwired. See ADR-007.
 *
 * Staging (test.akuru.edu.mv, 2026-08-25) has no `students_user_id_foreign`
 * even though school_id/class_id FKs exist — drop-by-conventional-name 1091s
 * and blocks every later migrate, including the 000003 backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        ForeignKeys::dropOnColumn('students', 'user_id');

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        if (! ForeignKeys::existsOnColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->ensureLegacyKeyUnique();
    }

    public function down(): void
    {
        $this->restoreLegacyKeyIndex();

        ForeignKeys::dropOnColumn('students', 'user_id');

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        if (! ForeignKeys::existsOnColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function ensureLegacyKeyUnique(): void
    {
        $hasUnique = false;

        foreach (Schema::getIndexes('students') as $index) {
            if (($index['columns'] ?? []) !== ['legacy_registration_student_id']) {
                continue;
            }

            if ($index['unique'] ?? false) {
                $hasUnique = true;

                continue;
            }

            Schema::table('students', function (Blueprint $table) use ($index): void {
                $table->dropIndex($index['name']);
            });
        }

        if (! $hasUnique) {
            Schema::table('students', function (Blueprint $table) {
                $table->unique('legacy_registration_student_id');
            });
        }
    }

    private function restoreLegacyKeyIndex(): void
    {
        foreach (Schema::getIndexes('students') as $index) {
            if (($index['columns'] ?? []) !== ['legacy_registration_student_id']) {
                continue;
            }

            Schema::table('students', function (Blueprint $table) use ($index): void {
                if ($index['unique'] ?? false) {
                    $table->dropUnique($index['name']);
                } else {
                    $table->dropIndex($index['name']);
                }
            });
        }

        Schema::table('students', function (Blueprint $table) {
            $table->index('legacy_registration_student_id');
        });
    }
};
