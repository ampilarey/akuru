<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('registration_fee_amount', 10, 2)->default(0)->after('fee');
            $table->string('registration_fee_currency', 3)->default('MVR')->after('registration_fee_amount');
            $table->boolean('requires_admin_approval')->default(true)->after('registration_fee_currency');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['registration_fee_amount', 'registration_fee_currency', 'requires_admin_approval']);
        });
    }
};
