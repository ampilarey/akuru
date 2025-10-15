<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add phone field if it doesn't exist
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
                $table->index('phone');
            }
            
            // OTP preferences
            $table->boolean('otp_enabled')->default(false)->after('remember_token');
            $table->boolean('two_factor_enabled')->default(false)->after('otp_enabled');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'otp_enabled',
                'two_factor_enabled',
                'phone_verified_at',
            ]);
            
            // Only drop phone if it was added by this migration
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropIndex(['phone']);
                $table->dropColumn('phone');
            }
        });
    }
};
