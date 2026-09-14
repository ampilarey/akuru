<?php

namespace Database\Seeders;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\People\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One distinctive row per slice that `STATUS.md` §2 records as **UNVERIFIED**,
 * so `scripts/smoke/sweep.mjs` can ask a harder question than "does the page
 * return 200".
 *
 * ## Why this exists
 *
 * A 116-screen load sweep (§5cl) already proved every staff screen answers.
 * What it could not prove is the failure this project keeps actually hitting:
 * *"CI-green slices still left empty grids"* — a screen that loads perfectly
 * and shows nothing, because the read path and the write path disagree about a
 * filter, a year, or a column.
 *
 * Every marker here starts `SMOKE-` and is unique, so the sweep looks for the
 * exact string it planted rather than for "some rows". A screen that renders a
 * table of other people's data while silently dropping this row fails.
 *
 * ## What it is not
 *
 * Not part of `DatabaseSeeder`, and never run by it. This is scaffolding for a
 * verification pass, invoked on purpose:
 *
 *     php artisan db:seed --class=SmokeMarkerSeeder
 *
 * It is idempotent — every insert deletes its own marker first — so the sweep
 * can be re-run without piling up duplicates.
 */
class SmokeMarkerSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::query()->where('status', 'active')->first();
        if ($year === null) {
            $this->command?->error('No active academic year — seed the app first.');

            return;
        }

        $class = ClassRoom::query()->where('academic_year_id', $year->id)->first();
        $admin = DB::table('users')->where('email', 'admin@akuru.edu.mv')->first();
        $studentId = (int) DB::table('class_student')->where('class_id', $class?->id)->value('student_id');
        $staff = StaffProfile::query()->first();

        $this->rooms($year, $admin);
        $this->calendar($year);
        $this->behaviour($year, $studentId, $admin);
        $this->standards();
        $this->awards($year, $studentId);
        $this->recruitment();
        $this->requests($admin);
        $this->finance($year, $studentId, $admin);
        $this->consent($studentId, $admin);
        $this->ownData($year, $studentId, $admin);

        // A default `migrate:fresh --seed` leaves `staff_profiles` empty, and
        // this used to skip the whole HR block in silence — so the sweep
        // afterwards reported five HR screens as not showing their rows, and
        // the screens were fine. A seeder that quietly plants nothing makes the
        // thing it is checking look broken, which is the worst direction for
        // the error to run.
        $staff ??= $this->makeStaffProfile();

        if ($staff !== null) {
            $this->hr($year, $staff, $admin);
        } else {
            $this->command?->warn('No staff profile and none could be made — the five HR markers were skipped.');
        }

        $this->command?->info('Smoke markers seeded. Run: node scripts/smoke/sweep.mjs');
    }

    /**
     * The HR screens need somebody to have a profile. Attached to the seeded
     * teacher rather than invented from nothing, so the rows the sweep plants
     * belong to a person the rest of the app already knows about.
     */
    private function makeStaffProfile(): ?StaffProfile
    {
        $userId = DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id')
            ?? DB::table('users')->value('id');

        if ($userId === null) {
            return null;
        }

        return StaffProfile::query()->create([
            'user_id' => $userId,
            'first_name' => 'Smoke',
            'last_name' => 'Marker',
            'gender' => 'female',
            'joined_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);
    }

    private function rooms(AcademicYear $year, ?object $admin): void
    {
        DB::table('room_bookings')->where('title', 'SMOKE-Booking')->delete();
        DB::table('rooms')->where('name', 'SMOKE-Room-A')->delete();

        $roomId = DB::table('rooms')->insertGetId([
            'name' => 'SMOKE-Room-A', 'type' => 'classroom', 'bookable' => 1, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('room_bookings')->insert([
            'room_id' => $roomId, 'academic_year_id' => $year->id, 'date' => now()->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'title' => 'SMOKE-Booking',
            'booked_by' => $admin?->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function calendar(AcademicYear $year): void
    {
        DB::table('calendar_days')->where('title', 'SMOKE-Holiday')->delete();
        DB::table('calendar_days')->insert([
            'academic_year_id' => $year->id, 'date' => now()->addDays(3)->toDateString(),
            'type' => 'holiday', 'title' => 'SMOKE-Holiday', 'affects_timetable' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function behaviour(AcademicYear $year, int $studentId, ?object $admin): void
    {
        DB::table('behavior_records')->where('category', 'SMOKE-Category')->delete();
        DB::table('behavior_records')->insert([
            'student_id' => $studentId, 'academic_year_id' => $year->id, 'type' => 'compliment',
            'category' => 'SMOKE-Category', 'description' => 'A smoke marker.',
            'date' => now()->toDateString(), 'recorded_by' => $admin?->id,
            'parent_visible' => 1, 'requires_followup' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function standards(): void
    {
        DB::table('standards')->where('title', 'SMOKE-Standard')->delete();
        DB::table('standards')->insert([
            'code' => 'SMOKE-STD-1', 'title' => 'SMOKE-Standard', 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function awards(AcademicYear $year, int $studentId): void
    {
        $existing = DB::table('awards')->where('title', 'SMOKE-Award')->pluck('id');
        DB::table('student_awards')->whereIn('award_id', $existing)->delete();
        DB::table('awards')->whereIn('id', $existing)->delete();

        $awardId = DB::table('awards')->insertGetId([
            'title' => 'SMOKE-Award', 'level' => 'school', 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('student_awards')->insert([
            'student_id' => $studentId, 'award_id' => $awardId, 'academic_year_id' => $year->id,
            'awarded_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function recruitment(): void
    {
        DB::table('job_postings')->where('title', 'SMOKE-Vacancy')->delete();
        DB::table('job_postings')->insert([
            'title' => 'SMOKE-Vacancy', 'description' => 'A smoke-test vacancy.',
            'status' => 'published', 'public' => 1, 'closes_at' => now()->addMonth(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function requests(?object $admin): void
    {
        DB::table('requests')->where('reason', 'SMOKE-Request')->delete();
        DB::table('requests')->insert([
            'type' => 'other', 'requester_id' => $admin?->id, 'reason' => 'SMOKE-Request',
            'status' => 'pending', 'payload' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function finance(AcademicYear $year, int $studentId, ?object $admin): void
    {
        DB::table('fee_structures')->where('name', 'SMOKE-Structure')->delete();
        DB::table('fee_structures')->insert([
            'academic_year_id' => $year->id, 'name' => 'SMOKE-Structure',
            'applies_to' => 'all_classes', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A distinctive percentage rather than a name: the adjustments screen
        // lists type and value, not a free-text label.
        DB::table('fee_adjustments')->where('value', 7.77)->delete();
        DB::table('fee_adjustments')->insert([
            'student_id' => $studentId, 'academic_year_id' => $year->id,
            'type' => 'scholarship', 'basis' => 'percent', 'value' => 7.77,
            'applies_to' => 'all_items', 'status' => 'approved', 'approved_by' => $admin?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A marker on the guardian's own child and a matching one on somebody
     * else's, so `scripts/smoke/own-data.mjs` can tell "the portal shows my
     * child" from "the portal shows every child".
     *
     * The second marker is the half that matters. A privacy check that only
     * looks for the right row passes just as happily on a page that lists the
     * whole school.
     */
    private function ownData(AcademicYear $year, int $studentId, ?object $admin): void
    {
        $other = (int) DB::table('students')->where('id', '!=', $studentId)->value('id');

        foreach ([[$studentId, 'JOURNEY-MINE'], [$other, 'JOURNEY-OTHER']] as [$id, $marker]) {
            if ($id <= 0) {
                continue;
            }

            DB::table('behavior_records')->where('category', $marker)->delete();
            DB::table('behavior_records')->insert([
                'student_id' => $id, 'academic_year_id' => $year->id, 'type' => 'compliment',
                'category' => $marker, 'description' => $marker.' note',
                'date' => now()->toDateString(), 'recorded_by' => $admin?->id,
                // Visible to families on purpose: a note the portal is supposed
                // to withhold proves nothing about whether it withholds.
                'parent_visible' => 1, 'requires_followup' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function consent(int $studentId, ?object $admin): void
    {
        DB::table('consents')->where('source', 'SMOKE-Source')->delete();
        DB::table('consents')->insert([
            'person_type' => 'student', 'person_id' => $studentId,
            'consent_type' => 'photo_media_use', 'granted' => 1,
            'granted_by' => $admin?->id, 'granted_at' => now(), 'source' => 'SMOKE-Source',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function hr(AcademicYear $year, StaffProfile $staff, ?object $admin): void
    {
        DB::table('staff_contracts')->where('basic_salary', 12345)->delete();
        DB::table('staff_contracts')->insert([
            'staff_profile_id' => $staff->id, 'contract_type' => 'permanent',
            'start_date' => '2026-01-01', 'basic_salary' => 12345, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cpd_records')->where('title', 'SMOKE-CPD')->delete();
        DB::table('cpd_records')->insert([
            'staff_profile_id' => $staff->id, 'title' => 'SMOKE-CPD',
            'provider' => 'SMOKE-Provider', 'hours' => 3, 'date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('lesson_observations')->where('summary', 'SMOKE-Observation')->delete();
        DB::table('lesson_observations')->insert([
            'staff_profile_id' => $staff->id, 'observer_id' => $admin?->id,
            'date' => now()->toDateString(), 'summary' => 'SMOKE-Observation',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('staff_attendance')->where('remarks', 'SMOKE-Attendance')->delete();
        DB::table('staff_attendance')->updateOrInsert(
            ['staff_profile_id' => $staff->id, 'date' => now()->toDateString()],
            [
                'academic_year_id' => $year->id, 'status' => 'present', 'source' => 'manual',
                'remarks' => 'SMOKE-Attendance', 'marked_by' => $admin?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]
        );

        // 333 entitled days is not a number any real policy produces, which is
        // the point — the balances screen either shows it or it does not.
        $typeId = (int) DB::table('leave_types')->where('code', 'annual')->value('id');
        if ($typeId > 0) {
            DB::table('leave_entitlements')->updateOrInsert(
                ['staff_profile_id' => $staff->id, 'leave_type_id' => $typeId, 'academic_year_id' => $year->id],
                ['entitled_days' => 333, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
