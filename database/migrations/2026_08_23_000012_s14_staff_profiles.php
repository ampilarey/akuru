<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1.4 — staff profiles + qualifications. Teacher rows stay as-is (Hifz frozen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('staff_number')->nullable();
            $table->string('first_name');
            $table->string('first_name_arabic')->nullable();
            $table->string('first_name_dhivehi')->nullable();
            $table->string('last_name');
            $table->string('last_name_arabic')->nullable();
            $table->string('last_name_dhivehi')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('national_id')->nullable();
            $table->string('passport', 50)->nullable();
            $table->string('nationality')->nullable()->default('MV');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->date('joined_date')->nullable();
            $table->string('employment_type', 32)->default('full_time');
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::create('staff_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->string('institution')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('staff_profile_id')->nullable()->after('user_id')->constrained('staff_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_profile_id');
        });
        Schema::dropIfExists('staff_qualifications');
        Schema::dropIfExists('staff_profiles');
    }
};
