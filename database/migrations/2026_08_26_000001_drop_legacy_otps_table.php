<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 audit cleanup — drop the pre-2026 `otps` table.
 *
 * PHASE_0_CHECKLIST called this a "duplicate create_otps_table migration". It is
 * not a duplicate: 2025_10_15_161251 creates `otps`, while the similarly-named
 * 2026_02_16_000002 creates `user_contact_otps`. Only the filenames matched.
 *
 * `otps` is dead: App\Domains\Identity\Models\Otp reads `user_contact_otps`, and
 * the sole remaining reference was a stale truncate in ClearNonAdminUsers.
 *
 * The 2025 migration is left in place rather than deleted — removing a migration
 * that has already run on an environment would desync its `migrations` table.
 * This drops the table forward instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('otps');
    }

    public function down(): void
    {
        if (Schema::hasTable('otps')) {
            return;
        }

        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');
            $table->string('code', 6);
            $table->enum('type', ['login', 'password_reset', 'verification', '2fa'])->default('login');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('attempts')->default(0);
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->index(['identifier', 'code', 'type']);
            $table->index('expires_at');
            $table->index('created_at');
        });
    }
};
