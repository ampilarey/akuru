<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('min_attendees')->nullable()->after('max_attendees');
            $table->boolean('waitlist_enabled')->default(false)->after('min_attendees');
            $table->boolean('requires_parent_confirmation')->default(false)->after('waitlist_enabled');
            $table->timestamp('second_round_opens_at')->nullable()->after('requires_parent_confirmation');
            $table->boolean('is_elective')->default(false)->after('second_round_opens_at');
            $table->foreignId('academic_year_id')->nullable()->after('is_elective')->constrained('academic_years')->nullOnDelete();
            $table->string('title_dv')->nullable()->after('title');
            $table->string('title_ar')->nullable()->after('title_dv');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('event_id')->constrained('students')->nullOnDelete();
            $table->foreignId('parent_user_id')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('waitlist_position')->nullable()->after('status');
            $table->foreignId('academic_year_id')->nullable()->after('waitlist_position')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('terms')->nullOnDelete();
        });

        DB::statement("ALTER TABLE event_registrations MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'attended', 'no_show', 'waitlisted', 'pending_parent') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE event_registrations MODIFY COLUMN registration_source ENUM('website', 'phone', 'email', 'walk_in', 'admin', 'portal') NOT NULL DEFAULT 'website'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE event_registrations MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'attended', 'no_show') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE event_registrations MODIFY COLUMN registration_source ENUM('website', 'phone', 'email', 'walk_in', 'admin') NOT NULL DEFAULT 'website'");

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropConstrainedForeignId('parent_user_id');
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn('waitlist_position');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn([
                'min_attendees',
                'waitlist_enabled',
                'requires_parent_confirmation',
                'second_round_opens_at',
                'is_elective',
                'title_dv',
                'title_ar',
            ]);
        });
    }
};
