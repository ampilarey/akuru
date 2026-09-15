<?php

namespace Database\Seeders;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Models\Lesson;
use App\Domains\People\Actions\EnsureLegacyStudentForUnifiedAction;
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
        $this->catalog();
        $this->learner($admin);
        $this->recruitment();
        $this->requests($admin);
        $this->readerWallet();
        $this->payableEnrolment($admin);
        $this->finance($year, $studentId, $admin);
        $this->consent($studentId, $admin);
        $this->ownData($year, $studentId, $admin);
        $this->sensitiveRecords($year, $studentId, $admin);
        $this->payslips($admin);
        $this->dailyLists($year, $studentId, $admin);

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

    /**
     * The three screens whose exports came back as a header row and nothing
     * else on the first browser walk: circulation, student work, pick-up.
     *
     * An empty table answers 200 and produces a well-formed CSV with no rows
     * in it, which is indistinguishable from a reader that silently returns
     * nothing. These markers make the difference visible.
     *
     * `student_work.photo_media_id` is NOT NULL, so the marker carries a
     * media row of its own rather than being forced past the constraint.
     */
    private function dailyLists(AcademicYear $year, int $studentId, ?object $admin): void
    {
        DB::table('book_titles')->where('title', 'SMOKE-Book')->delete();
        DB::table('book_titles')->insert([
            'title' => 'SMOKE-Book', 'author' => 'SMOKE-Author', 'isbn' => '9990000000001',
            'classification' => '297', 'language' => 'dv', 'loan_days' => 14,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // E8's **preconditions**, reset, not just its marker row.
        //
        // `pickup.mjs` opens with two gates that only exist before anything has
        // happened: a family cannot ask while pick-up is shut, and cannot ask
        // before they have set a PIN. Both are one-way through the UI — the PIN
        // has no unset, and the window from an earlier run is still open — so on
        // a second run those two steps failed against a system that was working
        // perfectly. The walk found it the first time the walks were run back to
        // back (STATUS §5ed).
        //
        // Clearing them here rather than in the walk is the same rule the rest
        // of this seeder follows: the fixture owns the state it depends on, and
        // a walk that has to undo the last run is a walk that tests the undo.
        DB::table('pickup_windows')->whereDate('date', now()->toDateString())->delete();
        DB::table('pickup_pins')->whereIn(
            'guardian_user_id',
            DB::table('users')->where('email', 'parent@akuru.edu.mv')->pluck('id')
        )->delete();

        DB::table('pickup_notices')->whereDate('date', now()->toDateString())->delete();
        DB::table('pickup_notices')->where('note', 'SMOKE-Pickup')->delete();
        DB::table('pickup_notices')->insert([
            'academic_year_id' => $year->id, 'student_id' => $studentId,
            'guardian_user_id' => $admin?->id, 'date' => now()->toDateString(),
            'status' => 'requested', 'requested_at' => now(), 'note' => 'SMOKE-Pickup',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('student_work')->where('title', 'SMOKE-Work')->delete();
        DB::table('media_files')->where('original_name', 'SMOKE-Work.jpg')->delete();

        $mediaId = DB::table('media_files')->insertGetId([
            'disk' => 'local', 'path' => 'smoke/work.jpg', 'original_name' => 'SMOKE-Work.jpg',
            'mime' => 'image/jpeg', 'size' => 1024, 'uploaded_by' => $admin?->id,
            'visibility' => 'private', 'process_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('student_work')->insert([
            'academic_year_id' => $year->id, 'student_id' => $studentId,
            'photo_media_id' => $mediaId, 'uploaded_by' => $admin?->id,
            'title' => 'SMOKE-Work', 'note' => 'SMOKE-Work-Note', 'done_on' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
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

    /**
     * The course engine's own catalog screens.
     *
     * Everything above this belongs to the S-track. The C-track — 1A catalog,
     * 1B offerings, the glossary and the taxonomy behind them — is the larger
     * half of the product and **no marker had ever been planted in it**, which
     * is why those §2 rows stayed UNVERIFIED while the S-track rows moved.
     *
     * A seeded database already has courses, levels and audiences, so a sweep
     * looking for "some rows" would have passed on all three while proving
     * nothing about whether *this* row reaches the screen. Hence a distinctive
     * marker in each, the same as everywhere else here.
     */
    private function catalog(): void
    {
        $categoryId = (int) DB::table('course_categories')->orderBy('id')->value('id');

        DB::table('course_offerings')->where('title', 'SMOKE-Offering')->delete();

        // The course is this seeder's own row, so it clears what hangs off it
        // before removing it. `learner()` plants a module, a lesson, a content
        // block, a published revision and an enrolment against this course, and
        // the second run of the seeder failed on the foreign key until this was
        // here — "every insert deletes its own marker first" has to mean the
        // marker *and its children*.
        //
        // It still did not, and the fix is not a longer list. **Seventeen
        // tables carry a `course_id` foreign key** — activities, assessments,
        // payments, leads, funnel events, testimonials, admission applications
        // — so deleting the course means chasing every one of them and every
        // table hanging off those in turn. The second run died first on
        // `activities` and then, with that added, on `funnel_events`, which is
        // written by the public registration walk and has nothing to do with
        // this fixture.
        //
        // So the course is **kept and updated** rather than dropped and
        // recreated. Its id is stable across runs, which is what everything
        // pointing at it needed all along; only the teaching content beneath
        // it is replaced, in `learner()`, where it is planted.
        $courseId = (int) DB::table('courses')->where('slug', 'smoke-course')->value('id');

        $course = [
            'course_category_id' => $categoryId,
            'title' => 'SMOKE-Course',
            'short_desc' => 'Planted by SmokeMarkerSeeder.',
            'body' => 'Planted by SmokeMarkerSeeder.',
            'cover_image' => '',
            'status' => 'open',
            // Two different columns say "published" here. `status` is what the
            // public site reads; `workflow_status` is what the **learner**
            // catalog filters on (`ListPublishedCoursesAction`). Setting only
            // the first left /learn/catalog empty while /learn showed the
            // course, which reads like a broken catalog until you find the
            // second column.
            'workflow_status' => 'published',
            'updated_at' => now(),
        ];

        if ($courseId > 0) {
            DB::table('courses')->where('id', $courseId)->update($course);
        } else {
            $courseId = DB::table('courses')->insertGetId($course + [
                'slug' => 'smoke-course',
                'created_at' => now(),
            ]);
        }

        DB::table('course_offerings')->insert([
            'course_id' => $courseId,
            'title' => 'SMOKE-Offering',
            'slug' => 'smoke-offering',
            // `delivery_mode` is a varchar carrying a PHP-enum cast, so the
            // database accepts anything and the screen 500s on read. The first
            // version of this marker said 'online', which is not a
            // `DeliveryMode` case, and the sweep reported /catalog/offerings as
            // HTTP 500 — a defect in the fixture, not the page. Checked before
            // it was written up as one.
            'delivery_mode' => 'self_learning',
            'status' => 'draft',
            'level_id' => DB::table('course_levels')->orderBy('id')->value('id'),
            'audience_id' => DB::table('audiences')->orderBy('id')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('glossary_items')->where('term', 'SMOKE-Term')->delete();
        DB::table('glossary_items')->insert([
            'term' => 'SMOKE-Term',
            'meaning_primary' => 'Planted by SmokeMarkerSeeder.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('course_levels')->where('name_en', 'SMOKE-Level')->delete();
        DB::table('course_levels')->insert([
            'name_en' => 'SMOKE-Level', 'slug' => 'smoke-level', 'sort_order' => 99, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('audiences')->where('name_en', 'SMOKE-Audience')->delete();
        DB::table('audiences')->insert([
            'name_en' => 'SMOKE-Audience', 'slug' => 'smoke-audience', 'sort_order' => 99, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A course a student can actually open — the learner path.
     *
     * `$this->catalog()` plants rows in the screens that *administer* the
     * course engine. This plants what a **student** sees, which nothing ever
     * had: before this the local database held **zero** modules, zero lessons,
     * zero content blocks, zero published revisions and zero enrolments. The
     * §2 rows for the outline, the blocks and `/learn` could not have been
     * walked, because there was nothing to walk.
     *
     * The lesson is published through `PublishLessonAction` rather than by
     * writing `snapshot_json` here. The player reads the **published
     * revision**, not the live blocks, so a hand-written snapshot would verify
     * the player against a fixture the application could never have produced —
     * and would skip the publish path entirely, which is the half most likely
     * to be wrong.
     */
    private function learner(?object $admin): void
    {
        $courseId = (int) DB::table('courses')->where('title', 'SMOKE-Course')->value('id');

        if ($courseId === 0) {
            return;
        }

        // Everything this method plants, cleared in foreign-key order so the
        // seeder can be run again — which it could not be until now. Attempts
        // go with the activities that own them: an attempt outliving its
        // activity sits in the review queue naming something that no longer
        // exists, which is a worse fixture than none.
        $lessonIds = DB::table('lessons')->where('course_id', $courseId)->pluck('id');
        $enrollmentIds = DB::table('course_enrollments')->where('course_id', $courseId)->pluck('id');

        DB::table('activity_attempts')->where('course_id', $courseId)->delete();
        DB::table('activities')->where('course_id', $courseId)->delete();
        DB::table('student_lesson_progress')->whereIn('enrollment_id', $enrollmentIds)->delete();
        DB::table('content_blocks')->where('course_id', $courseId)->delete();
        DB::table('lessons')->whereIn('id', $lessonIds)->update(['current_revision_id' => null]);
        DB::table('lesson_revisions')->whereIn('lesson_id', $lessonIds)->delete();
        DB::table('lessons')->whereIn('id', $lessonIds)->delete();
        DB::table('course_modules')->where('course_id', $courseId)->delete();

        $moduleId = DB::table('course_modules')->insertGetId([
            'course_id' => $courseId, 'title' => 'SMOKE-Module', 'position' => 1,
            'status' => 'published', 'created_by' => $admin?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $lessonId = DB::table('lessons')->insertGetId([
            'course_id' => $courseId, 'course_module_id' => $moduleId,
            'title' => 'SMOKE-Lesson', 'slug' => 'smoke-lesson', 'position' => 1,
            'status' => 'draft', 'created_by' => $admin?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Written through the authoring action, not inserted raw. The first
        // version of this did a raw insert with `data: {html: ...}` on a
        // `text` block, and the player showed an empty lesson — because a
        // `text` block's normalised shape is `{body: ...}` and only
        // `rich_text` carries `html`. That looked exactly like a player defect
        // for as long as it took to read the validator.
        //
        // `SaveContentBlockAction` runs `ValidateContentBlockDataAction`, so a
        // marker planted here cannot have a shape the application would refuse
        // — which is the whole point of a fixture that is meant to prove the
        // screen works.
        app(SaveContentBlockAction::class)->execute([
            'lesson_id' => $lessonId,
            'type' => 'text',
            'position' => 1,
            'title' => 'SMOKE-Block',
            'data' => ['body' => 'SMOKE-Lesson-Body'],
            'is_required' => true,
            'created_by' => $admin?->id,
        ]);

        app(PublishLessonAction::class)->execute(Lesson::query()->findOrFail($lessonId), $admin?->id);

        // One activity, so the walk can go past reading and actually answer
        // something. `selection` is the simplest of the four base patterns and
        // the only one that can be marked without a teacher, which is what
        // makes it walkable end to end by a student alone.
        //
        // Through `SaveActivityAction` for the same reason the content block
        // goes through its own action: the shape it validates is the shape the
        // player expects, and a raw insert is how this seeder produced an empty
        // lesson once already.
        app(SaveActivityAction::class)->execute([
            'course_id' => $courseId,
            'course_module_id' => $moduleId,
            'lesson_id' => $lessonId,
            'title' => 'SMOKE-Activity',
            'pattern' => 'selection',
            'max_score' => 1,
            'data' => [
                'prompt' => 'SMOKE-Question: which letter is a sun letter?',
                'options' => [
                    ['id' => 'a', 'label' => 'SMOKE-Right'],
                    ['id' => 'b', 'label' => 'SMOKE-Wrong'],
                ],
                'correct_ids' => ['a'],
            ],
            'created_by' => $admin?->id,
        ]);

        // And one the machine **cannot** mark. A `teacher_marked` activity is
        // the only pattern whose attempt lands `submitted` rather than
        // `scored`, which is what puts a row in the review queue — so it is
        // the only fixture that can walk the loop between a student handing
        // work in and a teacher handing a mark back.
        app(SaveActivityAction::class)->execute([
            'course_id' => $courseId,
            'course_module_id' => $moduleId,
            'lesson_id' => $lessonId,
            'title' => 'SMOKE-Review-Activity',
            'pattern' => 'teacher_marked',
            'max_score' => 5,
            'data' => [
                'prompt' => 'SMOKE-Review-Question: write a sentence using a sun letter.',
                'submission_kind' => 'written',
            ],
            'created_by' => $admin?->id,
        ]);

        // The enrolment the player checks. `unified_student_id` is what
        // AuthorizeLessonAccessAction matches on, resolved from the student
        // linked to the seeded student login.
        $studentUserId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');
        $unifiedStudentId = (int) DB::table('students')->where('user_id', $studentUserId)->value('id');

        if ($unifiedStudentId === 0 && $studentUserId > 0) {
            // **Nothing links a pupil to a login in the seeded data** — all
            // fifteen students had `user_id` null, so
            // `ResolveStudentForUserAction` answered null for the student
            // login and the entire learner path was unreachable to anybody
            // walking a seeded app. That includes the operator walking
            // staging, which is the one gate everything else waits on.
            //
            // Linked rather than invented, for the same reason
            // `makeStaffProfile()` attaches to the seeded teacher: the pupil
            // who opens the lesson should be somebody the rest of the app
            // already knows about, on a class roster, with attendance and a
            // report card.
            $unifiedStudentId = (int) DB::table('students')
                ->whereNull('user_id')
                ->orderBy('id')
                ->value('id');

            if ($unifiedStudentId > 0) {
                DB::table('students')->where('id', $unifiedStudentId)->update([
                    'user_id' => $studentUserId,
                    'updated_at' => now(),
                ]);
                $this->command?->info("Linked student #{$unifiedStudentId} to student@akuru.edu.mv — nothing else had.");
            }
        }

        if ($unifiedStudentId === 0) {
            $this->command?->warn('No student row for student@akuru.edu.mv — the learner enrolment was skipped.');

            return;
        }

        DB::table('course_enrollments')
            ->where('course_id', $courseId)
            ->where('unified_student_id', $unifiedStudentId)
            ->delete();

        // `course_enrollments.student_id` is **NOT NULL** and still points at
        // the legacy `registration_students` row, while `unified_student_id`
        // is the People one. That is the S1.1 dual-write era showing: an
        // enrolment cannot exist for a People-side pupil alone, and a seeded
        // database has **zero** `registration_students`, so there was no pupil
        // anywhere who could be enrolled on anything.
        //
        // The legacy row is created here rather than the column forced,
        // because that is what the real registration flow does. It is also the
        // concrete shape of the Deploy 3 cleanup that STATUS has been carrying
        // as a proposal: until `student_id` can go, this pairing is the only
        // way to enrol anybody.
        // Through People's own action rather than a hand-rolled insert. The
        // first version of this built the legacy row here and set the link
        // itself, duplicating `EnsureLegacyStudentForUnifiedAction`, which
        // already existed and does it better — it reuses a legacy row already
        // attached to the same login instead of making a second one. One
        // definition of "pair a unified pupil with a legacy row" (rule 11).
        $legacyId = app(EnsureLegacyStudentForUnifiedAction::class)->execute($unifiedStudentId);

        DB::table('course_enrollments')->insert([
            'course_id' => $courseId,
            'student_id' => $legacyId,
            'unified_student_id' => $unifiedStudentId,
            'status' => 'active',
            'enrolled_at' => now(),
            'created_by_user_id' => $admin?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Money the reader can actually spend.
     *
     * L6's earnings only exist behind a **paid** sale, and the only purchase
     * path that completes without BML is the wallet branch (§43.14) — internal
     * money, so it grants immediately rather than waiting on a webhook. With
     * `BML_WEBHOOK_SECRET` unset, which is the state of every environment right
     * now (`OWNER_ACTIONS` item 2), a wallet balance is the only way anybody can
     * buy anything at all.
     *
     * Topped up to a round figure rather than set: `CreditWalletAction` is the
     * one way money enters a wallet (rule 12) and the ledger is append-only, so
     * the seeder adds what is missing instead of writing a balance.
     */
    private function readerWallet(): void
    {
        $userId = (int) DB::table('users')->where('email', 'parent@akuru.edu.mv')->value('id');

        if ($userId === 0) {
            return;
        }

        $balance = (float) (DB::table('wallets')->where('user_id', $userId)->value('balance') ?? 0);
        $target = 500.0;

        if ($balance < $target) {
            app(CreditWalletAction::class)->execute(
                $userId,
                round($target - $balance, 2),
                'smoke_marker',
                null,
                'SMOKE-Wallet top-up so a reader can buy a priced library item.',
            );
        }
    }

    /**
     * Something to take money for, and something to give back.
     *
     * §1f's money surfaces need an enrolment **awaiting payment**, and a seeded
     * database has none — so the manual-payment and refund screens could only
     * ever be looked at, never used. While `BML_WEBHOOK_SECRET` is unset
     * (`OWNER_ACTIONS` item 2) a manual payment is the **only** way the school
     * can take money at all, which makes this the least optional fixture here.
     *
     * Rebuilt each run rather than topped up: the walk pays it and then refunds
     * it, and both of those are one-way. `payment_status` starts `pending`,
     * which is what puts the *Record manual payment* form on the screen.
     */
    private function payableEnrolment(?object $admin): void
    {
        $categoryId = (int) DB::table('course_categories')->orderBy('id')->value('id');

        $courseId = (int) DB::table('courses')->where('slug', 'smoke-payable')->value('id');
        $course = [
            'course_category_id' => $categoryId,
            'title' => 'SMOKE-Payable-Course',
            'short_desc' => 'Planted by SmokeMarkerSeeder for the money walk.',
            'body' => 'Planted by SmokeMarkerSeeder for the money walk.',
            'cover_image' => '',
            'status' => 'open',
            'workflow_status' => 'published',
            'registration_fee_amount' => 250,
            'requires_admin_approval' => 0,
            'updated_at' => now(),
        ];

        if ($courseId > 0) {
            DB::table('courses')->where('id', $courseId)->update($course);
        } else {
            $courseId = DB::table('courses')->insertGetId($course + ['slug' => 'smoke-payable', 'created_at' => now()]);
        }

        $studentUserId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');
        $unifiedStudentId = (int) DB::table('students')->where('user_id', $studentUserId)->value('id');

        if ($unifiedStudentId === 0) {
            return;
        }

        $legacyId = app(EnsureLegacyStudentForUnifiedAction::class)->execute($unifiedStudentId);

        // Everything the previous run left behind. A refunded enrolment cannot
        // be un-refunded, so the row is replaced rather than reset — and the
        // payments it spawned go with it, or the walk would refund one of them
        // a second time and report a product fault that is its own mess.
        $stale = DB::table('course_enrollments')->where('course_id', $courseId)->pluck('id');
        DB::table('payment_items')->whereIn('enrollment_id', $stale)->delete();
        DB::table('course_enrollments')->whereIn('id', $stale)->delete();

        $paymentIds = DB::table('payments')->where('course_id', $courseId)->pluck('id');
        DB::table('payment_refunds')->whereIn('payment_id', $paymentIds)->delete();
        DB::table('payment_items')->whereIn('payment_id', $paymentIds)->delete();
        DB::table('payments')->whereIn('id', $paymentIds)->delete();

        DB::table('course_enrollments')->insert([
            'course_id' => $courseId,
            'student_id' => $legacyId,
            'unified_student_id' => $unifiedStudentId,
            'status' => 'pending',
            'payment_status' => 'pending',
            'enrolled_at' => now(),
            'created_by_user_id' => $admin?->id,
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
