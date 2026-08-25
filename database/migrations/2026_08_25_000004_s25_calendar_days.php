<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S2.5 — school calendar days. No register generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 32);
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->boolean('affects_timetable')->default(true);
            $table->unsignedBigInteger('event_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['date', 'academic_year_id']);
            $table->index(['academic_year_id', 'type']);
        });

        Schema::table('calendar_days', function (Blueprint $table) {
            if (Schema::hasTable('events')) {
                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            }
        });

        Permission::firstOrCreate(['name' => 'calendar.manage', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo('calendar.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
    }
};
