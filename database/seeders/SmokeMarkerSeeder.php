<?php

namespace Database\Seeders;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\People\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $this->sensitiveRecords($year, $studentId, $admin);
        $this->payslips($admin);

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

    /**
     * The records a family would mind most: a report card, a receipt, a
     * private message thread — one belonging to the guardian's own child and
     * one to somebody else's.
     *
     * These tables are **empty in the default seed**, which is why
     * `own-data.mjs` could not probe them: a route asked for a row that does
     * not exist answers 404, and a 404 proves nothing about who may read it.
     * An empty table is the easiest way for a privacy check to look clean.
     *
     * Payroll is included for the same reason and is the sharpest case — a
     * payslip is the one record in this system that an ordinary member of staff
     * must not be able to read about a colleague.
     */
    private function sensitiveRecords(AcademicYear $year, int $studentId, ?object $admin): void
    {
        $other = (int) DB::table('students')->where('id', '!=', $studentId)->value('id');
        $term = (int) DB::table('terms')->where('academic_year_id', $year->id)->value('id');
        $class = (int) DB::table('class_student')->where('student_id', $studentId)->value('class_id');

        if ($term > 0 && $class > 0) {
            $templateId = DB::table('report_card_templates')->where('name', 'SMOKE-Template')->value('id')
                ?? DB::table('report_card_templates')->insertGetId([
                    'name' => 'SMOKE-Template',
                    'sections' => json_encode(['grades_table']),
                    'active' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);

            foreach ([$studentId, $other] as $id) {
                if ($id <= 0) {
                    continue;
                }

                // **With a document.** `DownloadPublishedReportCardAction`
                // checks `document_id` *before* it checks whose child this is,
                // so a card with no document refuses everybody — and a privacy
                // probe against one proves nothing at all. The first version
                // of this seeder planted cards without documents and the probe
                // reported a clean 404 for another family's card, which was
                // the route declining to answer rather than the rule working.
                $path = 'smoke/report-card-'.$id.'.html';
                Storage::disk('local')->put($path, '<p>SMOKE report card for student '.$id.'</p>');

                $documentId = DB::table('documents')->where('media_path', $path)->value('id')
                    ?? DB::table('documents')->insertGetId([
                        'documentable_type' => 'report_card', 'documentable_id' => $id,
                        'media_path' => $path, 'document_type' => 'other',
                        'title' => 'SMOKE report card', 'uploaded_by' => $admin?->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);

                DB::table('report_cards')->updateOrInsert(
                    ['student_id' => $id, 'term_id' => $term],
                    [
                        'class_id' => $class, 'template_id' => $templateId, 'status' => 'published',
                        'document_id' => $documentId,
                        'generated_at' => now(), 'published_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]
                );
            }
        }

        // A receipt against whichever invoice exists, so the document route has
        // something real to refuse.
        $invoiceId = (int) DB::table('invoices')->value('id');
        if ($invoiceId > 0) {
            DB::table('receipts')->updateOrInsert(
                ['receipt_number' => 'SMOKE-RCPT-1'],
                [
                    'invoice_id' => $invoiceId, 'amount' => 100, 'method' => 'cash',
                    'received_by' => $admin?->id, 'received_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }

        // A thread between staff and one guardian. The other family must not
        // be able to open it.
        $guardianUserId = (int) DB::table('user_contacts')
            ->where('value', 'parent@akuru.edu.mv')->value('user_id');

        if ($guardianUserId > 0 && $admin !== null) {
            $threadId = DB::table('message_threads')->where('subject', 'SMOKE-Thread')->value('id')
                ?? DB::table('message_threads')->insertGetId([
                    'subject' => 'SMOKE-Thread', 'created_by' => $admin->id,
                    'reply_policy' => 'all', 'last_message_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            foreach ([[$admin->id, 'staff'], [$guardianUserId, 'guardian']] as [$userId, $role]) {
                DB::table('message_participants')->updateOrInsert(
                    ['message_thread_id' => $threadId, 'user_id' => $userId],
                    ['role' => $role, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            // And one the guardian is deliberately **not** in. Without it the
            // only thread in the database is one they are allowed to read, and
            // "can they open a thread that is not theirs" has nothing to ask.
            $otherThreadId = DB::table('message_threads')->where('subject', 'SMOKE-Thread-Not-Mine')->value('id')
                ?? DB::table('message_threads')->insertGetId([
                    'subject' => 'SMOKE-Thread-Not-Mine', 'created_by' => $admin->id,
                    'reply_policy' => 'all', 'last_message_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('message_participants')->updateOrInsert(
                ['message_thread_id' => $otherThreadId, 'user_id' => $admin->id],
                ['role' => 'staff', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Two payslips, for two different members of staff, each with a document.
     *
     * A payslip is the single record in this system that an ordinary colleague
     * must not be able to read — and it was the last of the sensitive tables
     * left empty, so `own-data.mjs` had nothing to ask about it. Payroll ships
     * flag-off, which is why nothing else creates these; the rows are inserted
     * directly rather than by running payroll, because the question here is who
     * may **read** one, not how one is calculated.
     *
     * `PayslipDocumentController` checks ownership *before* it checks the
     * document — the opposite order to the report-card route that made the
     * earlier probe vacuous — but the owner still needs a document to get a
     * 200, so both halves of the pair need one.
     */
    private function payslips(?object $admin): void
    {
        $staff = StaffProfile::query()->orderBy('id')->take(2)->get();

        if ($staff->count() < 2) {
            $staff->push($this->makeSecondStaffProfile());
            $staff = $staff->filter();
        }

        if ($staff->count() < 2) {
            $this->command?->warn('Fewer than two staff profiles — the payslip pair was skipped.');

            return;
        }

        $periodId = DB::table('payroll_periods')->where('year', 2026)->where('month', 8)->value('id')
            ?? DB::table('payroll_periods')->insertGetId([
                'year' => 2026, 'month' => 8, 'status' => 'draft',
                'processed_by' => $admin?->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

        foreach ($staff as $profile) {
            $path = 'smoke/payslip-'.$profile->id.'.html';
            Storage::disk('local')->put($path, '<p>SMOKE payslip for staff '.$profile->id.'</p>');

            $documentId = DB::table('documents')->where('media_path', $path)->value('id')
                ?? DB::table('documents')->insertGetId([
                    'documentable_type' => 'payslip', 'documentable_id' => $profile->id,
                    'media_path' => $path, 'document_type' => 'other',
                    'title' => 'SMOKE payslip', 'uploaded_by' => $admin?->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('payslips')->updateOrInsert(
                ['payroll_period_id' => $periodId, 'staff_profile_id' => $profile->id],
                [
                    'basic_salary' => 10000, 'gross' => 10000, 'net_pay' => 10000,
                    'employee_pension' => 0, 'employer_pension' => 0, 'tax_withheld' => 0,
                    'unpaid_leave_deduction' => 0, 'document_id' => $documentId,
                    'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * A second member of staff, so "can one colleague read another's payslip"
     * has two people to ask about.
     */
    private function makeSecondStaffProfile(): ?StaffProfile
    {
        $taken = StaffProfile::query()->pluck('user_id');
        $userId = DB::table('user_contacts')
            ->whereIn('value', ['teacher.quran@akuru.edu.mv', 'headmaster@akuru.edu.mv', 'supervisor@akuru.edu.mv'])
            ->whereNotIn('user_id', $taken)
            ->value('user_id');

        if ($userId === null) {
            return null;
        }

        return StaffProfile::query()->create([
            'user_id' => $userId,
            'first_name' => 'Smoke', 'last_name' => 'Colleague',
            'gender' => 'male', 'joined_date' => '2026-01-01',
            'employment_type' => 'full_time', 'status' => 'active',
        ]);
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
