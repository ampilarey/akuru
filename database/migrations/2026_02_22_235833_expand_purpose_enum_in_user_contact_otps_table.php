<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `user_contact_otps` MODIFY COLUMN `purpose` ENUM('verify_contact','password_reset','login','enroll') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `user_contact_otps` MODIFY COLUMN `purpose` ENUM('verify_contact','password_reset') NOT NULL");
    }
};
