<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * D2 — parent-teacher meeting slots (time-scoped) and bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedBigInteger('teacher_id');
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'date']);
            $table->index(['teacher_id', 'date']);
            $table->index('status');
        });

        Schema::create('meeting_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_slot_id')->constrained('meeting_slots')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('booked_by')->nullable();
            $table->string('status', 32)->default('booked');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['meeting_slot_id', 'student_id']);
            $table->index(['academic_year_id', 'student_id']);
        });

        Permission::firstOrCreate(['name' => 'meetings.manage', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo('meetings.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_bookings');
        Schema::dropIfExists('meeting_slots');
    }
};
