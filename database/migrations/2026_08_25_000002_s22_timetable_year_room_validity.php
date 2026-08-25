<?php

use App\Domains\Academics\Actions\BackfillTimetableYearAndRoomsAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S2.2 — timetable year/term/room_id/validity (additive). Legacy room
 * strings and start_date/end_date stay. No student-keyed writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('class_id')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('terms')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->after('period_id')->constrained('rooms')->nullOnDelete();
            $table->date('valid_from')->nullable()->after('end_date');
            $table->date('valid_until')->nullable()->after('valid_from');
        });

        app(BackfillTimetableYearAndRoomsAction::class)->execute();

        Permission::firstOrCreate(['name' => 'timetables.allow_conflict', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo('timetables.allow_conflict');
        }
    }

    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn(['valid_from', 'valid_until']);
        });
    }
};
