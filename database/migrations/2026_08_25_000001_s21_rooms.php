<?php

use App\Domains\Academics\Actions\SyncRoomsFromTimetableStringsAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S2.1 — rooms as first-class entities (no timetable schema change).
 *
 * Central database/migrations/ (domain folders not loaded). Backfill creates
 * rooms from distinct timetables.room strings; legacy string columns stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->string('building')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('type', 32)->default('classroom');
            $table->boolean('bookable')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Permission::firstOrCreate(['name' => 'rooms.manage', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo('rooms.manage');
        }

        app(SyncRoomsFromTimetableStringsAction::class)->execute();
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
