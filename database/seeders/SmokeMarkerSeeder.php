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
        $this->feeCycle($year, $class, $studentId, $admin);
        $this->consent($studentId, $admin);
        $this->ownData($year, $studentId, $admin);
        $this->sensitiveRecords($year, $studentId, $admin);
        $this->payslips($admin);
        $this->dailyLists($year, $studentId, $admin);
        $this->pronunciation();
        $this->recitations();
        $this->examCycle($year);
        $this->hrCycle($year, $admin);
        $this->authorCycle();
        $this->intakeCycle();
        $this->assessCycle();
        $this->certifyCycle();
        $this->buyCycle($admin);

        // A default `migrate:fresh --seed` leaves `staff_profiles` empty, and
        // this used to skip the whole HR block in silence — so the sweep
        // afterwards reported five HR screens as not showing their rows, and
        // the screens were fine. A seeder that quietly plants nothing makes the
        // thing it is checking look broken, which is the worst direction for
        // the error to run.
        // `hrCycle()` may have just made teacher@'s profile on an empty table,
        // so look again before making one (user_id is unique).
        $staff ??= StaffProfile::query()->first() ?? $this->makeStaffProfile();

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
        $this->topUpWallet('parent@akuru.edu.mv', 500.0, 'SMOKE-Wallet top-up so a reader can buy a priced library item.');
    }

    private function topUpWallet(string $email, float $target, string $reason): void
    {
        $userId = (int) DB::table('users')->where('email', $email)->value('id');

        if ($userId === 0) {
            return;
        }

        $balance = (float) (DB::table('wallets')->where('user_id', $userId)->value('balance') ?? 0);

        if ($balance < $target) {
            app(CreditWalletAction::class)->execute(
                $userId,
                round($target - $balance, 2),
                'smoke_marker',
                null,
                $reason,
            );
        }
    }

    /**
     * `scripts/smoke/buy.mjs` has the student buy `SMOKE-Wallet-Course`
     * (MVR 100) from the learner catalog with the `SMOKE-OFF` coupon and
     * their wallet. This keeps the course — with one published lesson, so
     * "locked before, open after" has something to lock — and clears what a
     * run leaves: the student's enrolment on it, the coupon and its
     * redemptions; and tops the student's wallet back up to 500. The wallet
     * ledger is append-only (rule 12), so the top-up is a credit, never a
     * reset, and last run's purchase stays in the history.
     */
    private function buyCycle(?object $admin): void
    {
        $courseId = (int) DB::table('courses')->where('slug', 'smoke-wallet-course')->value('id');
        $course = [
            'course_category_id' => DB::table('course_categories')->orderBy('id')->value('id'),
            'title' => 'SMOKE-Wallet-Course',
            'short_desc' => 'Planted by SmokeMarkerSeeder: MVR 100, for the purchase walk.',
            'body' => 'Planted by SmokeMarkerSeeder.',
            'cover_image' => '',
            'status' => 'open',
            'workflow_status' => 'published',
            'fee' => 100,
            'registration_fee_amount' => 100,
            // The column defaults to true, which holds a paid enrolment at
            // `pending` for the office to approve — a separate, tested gate.
            // This walk is about the payment opening the course, so the gate
            // is off here.
            'requires_admin_approval' => false,
            'updated_at' => now(),
        ];
        if ($courseId > 0) {
            DB::table('courses')->where('id', $courseId)->update($course);
        } else {
            $courseId = DB::table('courses')->insertGetId($course + ['slug' => 'smoke-wallet-course', 'created_at' => now()]);
        }

        // Residue, in foreign-key order; then the lesson, replaced each run
        // the way `learner()` replaces SMOKE-Course's.
        $enrollmentIds = DB::table('course_enrollments')->where('course_id', $courseId)->pluck('id');
        $lessonIds = DB::table('lessons')->where('course_id', $courseId)->pluck('id');
        DB::table('student_lesson_progress')->whereIn('enrollment_id', $enrollmentIds)->delete();
        DB::table('discount_redemptions')->where('purchase_type', 'course_enrollment')->whereIn('purchase_id', $enrollmentIds)->delete();
        DB::table('course_enrollments')->whereIn('id', $enrollmentIds)->delete();
        DB::table('content_blocks')->where('course_id', $courseId)->delete();
        DB::table('lessons')->whereIn('id', $lessonIds)->update(['current_revision_id' => null]);
        DB::table('lesson_revisions')->whereIn('lesson_id', $lessonIds)->delete();
        DB::table('lessons')->whereIn('id', $lessonIds)->delete();
        DB::table('course_modules')->where('course_id', $courseId)->delete();

        $codeIds = DB::table('discount_codes')->where('code', 'SMOKE-OFF')->pluck('id');
        DB::table('discount_redemptions')->whereIn('discount_code_id', $codeIds)->delete();
        DB::table('discount_codes')->whereIn('id', $codeIds)->delete();

        $moduleId = DB::table('course_modules')->insertGetId([
            'course_id' => $courseId, 'title' => 'SMOKE-Wallet-Module', 'position' => 1,
            'status' => 'published', 'created_by' => $admin?->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lessonId = DB::table('lessons')->insertGetId([
            'course_id' => $courseId, 'course_module_id' => $moduleId,
            'title' => 'SMOKE-Wallet-Lesson', 'slug' => 'smoke-wallet-lesson', 'position' => 1,
            'status' => 'draft', 'created_by' => $admin?->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SaveContentBlockAction::class)->execute([
            'lesson_id' => $lessonId, 'type' => 'text', 'position' => 1, 'title' => 'SMOKE-Wallet-Block',
            'data' => ['body' => 'SMOKE-Wallet-Lesson-Body'], 'is_required' => true, 'created_by' => $admin?->id,
        ]);
        app(PublishLessonAction::class)->execute(Lesson::query()->findOrFail($lessonId), $admin?->id);

        $this->topUpWallet('student@akuru.edu.mv', 500.0, 'SMOKE-Wallet top-up so a student can buy a priced course.');
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

        // §1f's last line needs something to set an override *on*.
        //
        // Found the way the product finds it — the **lowest-id self-learning
        // offering of this course**, which is exactly what
        // `DefaultSelfLearningOfferingAction` reads — rather than by slug.
        //
        // Keying on the slug looked obvious and was wrong: saving an offering
        // through the admin form regenerates the slug from the title, so the
        // walk's own first save renamed the row out from under the key. The
        // next seed then matched nothing, inserted a *second* offering, and the
        // catalog went on reading the first one — still carrying the previous
        // run's `0` override. The walk reported a course advertised as free
        // that the seeder had just reset to 250, and the product was right
        // both times. A fixture must be keyed on something the walk cannot
        // change.
        $offeringId = (int) DB::table('course_offerings')
            ->where('course_id', $courseId)
            ->where('delivery_mode', 'self_learning')
            ->orderBy('id')
            ->value('id');

        $offering = [
            'title' => 'SMOKE-Payable-Offering',
            'slug' => 'smoke-payable-offering',
            'delivery_mode' => 'self_learning',
            'status' => 'open',
            'pin_mode' => 'latest',
            // Reset, not defaulted: the walk leaves an override of 0 behind,
            // and the first thing it asserts next run is the un-overridden
            // price.
            'price_override' => null,
            'updated_at' => now(),
        ];

        if ($offeringId > 0) {
            DB::table('course_offerings')->where('id', $offeringId)->update($offering);
        } else {
            DB::table('course_offerings')->insert($offering + ['course_id' => $courseId, 'created_at' => now()]);
        }
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
        // The year's first real term, by id — never `SMOKE-Term`, which
        // `examCycle()` plants after this and keeps empty of published cards.
        $term = (int) DB::table('terms')->where('academic_year_id', $year->id)->orderBy('id')->value('id');
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

        // A receipt against the planted invoice (`feeCycle()`), so the document
        // route has something real to refuse. Before that it took "whichever
        // invoice exists", and the default seed has none, so the marker was
        // silently skipped on every fresh database.
        $invoiceId = (int) (DB::table('invoices')->where('invoice_number', 'SMOKE-INV-1')->value('id')
            ?? DB::table('invoices')->value('id'));
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
    /**
     * §1d's preconditions, cleared — not a marker row.
     *
     * The pronunciation walk records an attempt, has a teacher verify it, and
     * has an admin approve the training sample that verdict creates. All three
     * queues are ordered oldest-first and every one of those steps is one-way,
     * so on a second run the walk would be reviewing the *first* run's attempt
     * while its own sat at the bottom of the queue — and the counts it measures
     * would drift by one each time.
     *
     * Clearing this student's attempts leaves the queue holding exactly what
     * the walk is about to put in it, which is the same rule the pick-up
     * preconditions follow: the fixture owns the state it depends on.
     */
    private function pronunciation(): void
    {
        $studentUserId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');

        if ($studentUserId === 0) {
            return;
        }

        $attemptIds = DB::table('arabic_pronunciation_attempts')
            ->where('student_user_id', $studentUserId)
            ->pluck('id');

        if ($attemptIds->isEmpty()) {
            return;
        }

        // The audio each one carries, gathered before the rows naming it go.
        $mediaIds = DB::table('arabic_pronunciation_attempts')
            ->whereIn('id', $attemptIds)
            ->pluck('audio_media_file_id')
            ->merge(
                DB::table('training_samples')
                    ->whereIn('arabic_pronunciation_attempt_id', $attemptIds)
                    ->pluck('audio_media_file_id')
            )
            ->filter()
            ->unique();

        DB::table('training_samples')->whereIn('arabic_pronunciation_attempt_id', $attemptIds)->delete();
        DB::table('arabic_pronunciation_attempts')->whereIn('id', $attemptIds)->delete();
        DB::table('media_files')->whereIn('id', $mediaIds)->delete();
    }

    /**
     * §1e's preconditions, cleared for the same reason §1d's are.
     *
     * The recitation queue is oldest-first and reviewing is one-way, so on a
     * second run the walk would be marking the first run's recitation while its
     * own waited at the bottom. Clearing this student's submissions leaves the
     * queue holding exactly what the walk is about to put in it.
     */
    private function recitations(): void
    {
        $studentUserId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');
        $studentId = (int) DB::table('students')->where('user_id', $studentUserId)->value('id');

        if ($studentId === 0) {
            return;
        }

        $submissionIds = DB::table('quran_recitation_submissions')
            ->where('student_id', $studentId)
            ->pluck('id');

        if ($submissionIds->isEmpty()) {
            return;
        }

        $mediaIds = DB::table('quran_recitation_submissions')
            ->whereIn('id', $submissionIds)
            ->pluck('audio_media_file_id')
            ->merge(
                DB::table('quran_recitation_submissions')
                    ->whereIn('id', $submissionIds)
                    ->pluck('correction_audio_media_file_id')
            )
            ->filter()
            ->unique();

        DB::table('quran_mistake_marks')->whereIn('quran_recitation_submission_id', $submissionIds)->delete();
        DB::table('ai_predictions')->whereIn('quran_recitation_submission_id', $submissionIds)->update([
            'quran_recitation_submission_id' => null,
        ]);
        DB::table('quran_recitation_submissions')->whereIn('id', $submissionIds)->delete();
        DB::table('media_files')->whereIn('id', $mediaIds)->delete();
    }

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

        // A *paid* period with *final* payslips: the shape a period with
        // documents on it actually has. This planted `status => 'draft'`, a
        // value `PayrollPeriodStatus` does not have, so the first read of the
        // row through the model — the payroll screen's period list — threw a
        // ValueError and the screen answered 500 on every seeded database.
        // Nothing had loaded that screen with the flag on until the HR walk
        // did (STATUS §5fd).
        $periodId = DB::table('payroll_periods')->where('year', 2026)->where('month', 8)->value('id')
            ?? DB::table('payroll_periods')->insertGetId([
                'year' => 2026, 'month' => 8, 'status' => 'paid',
                'processed_by' => $admin?->id, 'approved_by' => $admin?->id, 'paid_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        DB::table('payroll_periods')->where('id', $periodId)->whereNotIn('status', ['open', 'processing', 'review', 'approved', 'paid', 'locked'])
            ->update(['status' => 'paid', 'paid_at' => now()]);

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
                    'status' => 'final', 'created_at' => now(), 'updated_at' => now(),
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

    /**
     * The fee cycle's own class, and nothing left from the last run.
     *
     * `scripts/smoke/fees.mjs` builds a fee structure, generates and issues an
     * invoice, sets up a plan, records cash and reads the receipt. Two rules
     * stop it running against what is already there: one active structure
     * per class per year (the pilot's covers the child's class), and
     * generation is idempotent per student, structure and period. So the
     * child is also enrolled in `SMOKE-Class A`, which nothing else covers,
     * and everything the walk made last time — the structure it named
     * `SMOKE-Fees`, its invoices, lines, generation log, plan, receipts and
     * their documents — is removed here before it runs again.
     *
     * Also plants `SMOKE-INV-1` with a plan on it, so the payment-plans
     * screen has a row for `sweep.mjs` to find (it had none — STATUS §2 said
     * "a load, not a data check" for S4.4).
     */
    private function feeCycle(AcademicYear $year, ?ClassRoom $class, int $studentId, ?object $admin): void
    {
        if ($class === null || $studentId <= 0 || $admin === null) {
            $this->command?->warn('No class, student or admin — the fee-cycle markers were skipped.');

            return;
        }

        $classId = (int) (DB::table('classes')
            ->where('academic_year_id', $year->id)->where('name', 'SMOKE-Class')->where('section', 'A')
            ->value('id')
            ?? DB::table('classes')->insertGetId([
                'school_id' => $class->school_id, 'academic_year_id' => $year->id,
                'name' => 'SMOKE-Class', 'section' => 'A', 'level' => $class->level, 'capacity' => 5,
                'description' => 'The fees walk\'s own class: no other fee structure may cover it.',
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]));
        DB::table('class_student')->updateOrInsert(
            ['class_id' => $classId, 'student_id' => $studentId],
            [
                'academic_year_id' => $year->id, 'enrolled_at' => now()->subDays(30)->toDateString(),
                'left_at' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ],
        );

        $structureIds = DB::table('fee_structures')->where('name', 'SMOKE-Fees')->pluck('id');
        $invoiceIds = DB::table('invoices')
            ->where(fn ($q) => $q->whereIn(DB::raw("JSON_EXTRACT(meta, '$.fee_structure_id')"), $structureIds->all() ?: [0])
                ->orWhere('invoice_number', 'SMOKE-INV-1'))
            ->pluck('id');
        $this->forgetInvoices($invoiceIds->all());
        DB::table('invoices')->whereIn('id', $invoiceIds)->where('invoice_number', '!=', 'SMOKE-INV-1')->delete();
        DB::table('fee_structures')->whereIn('id', $structureIds)->delete();

        // The sweep's plan: an invoice a third paid, the rest on two installments.
        DB::table('invoices')->updateOrInsert(
            ['invoice_number' => 'SMOKE-INV-1'],
            [
                'student_id' => $studentId, 'academic_year_id' => $year->id, 'invoice_type' => 'school_fees',
                'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
                'status' => 'sent', 'subtotal' => 300, 'total_amount' => 300, 'paid_amount' => 100,
                // The child's real class, not `SMOKE-Class`: collections groups
                // by class and month, and the fees walk reads its own class's row.
                'notes' => 'SMOKE-INV-1', 'meta' => json_encode(['class_id' => $class->id, 'period_key' => 'smoke']),
                'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ],
        );
        $invoiceId = (int) DB::table('invoices')->where('invoice_number', 'SMOKE-INV-1')->value('id');
        $planId = DB::table('payment_plans')->insertGetId([
            'invoice_id' => $invoiceId, 'total_amount' => 200, 'status' => 'active',
            'created_by' => $admin->id, 'approved_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2] as $sequence) {
            DB::table('payment_plan_installments')->insert([
                'payment_plan_id' => $planId, 'sequence' => $sequence,
                'due_date' => now()->addMonths($sequence)->toDateString(), 'amount' => 100, 'paid_amount' => 0,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('invoices')->where('id', $invoiceId)->update(['payment_plan_id' => $planId]);
    }

    /**
     * Everything that hangs off an invoice, in foreign-key order. The invoice
     * row itself is the caller's to keep or delete.
     *
     * @param  list<int>  $invoiceIds
     */
    private function forgetInvoices(array $invoiceIds): void
    {
        if ($invoiceIds === []) {
            return;
        }

        $receiptIds = DB::table('receipts')->whereIn('invoice_id', $invoiceIds)->pluck('id');
        DB::table('documents')->where('documentable_type', 'receipt')->whereIn('documentable_id', $receiptIds)->delete();
        DB::table('receipts')->whereIn('id', $receiptIds)->delete();
        DB::table('invoices')->whereIn('id', $invoiceIds)->update(['payment_plan_id' => null]);
        $planIds = DB::table('payment_plans')->whereIn('invoice_id', $invoiceIds)->pluck('id');
        DB::table('payment_plan_installments')->whereIn('payment_plan_id', $planIds)->delete();
        DB::table('payment_plans')->whereIn('id', $planIds)->delete();
        DB::table('invoice_generation_logs')->whereIn('invoice_id', $invoiceIds)->delete();
        DB::table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->delete();
    }

    /**
     * A term of the walk's own, and nothing in it.
     *
     * `scripts/smoke/exams.mjs` schedules an exam, enters a mark, publishes,
     * generates report cards and publishes those — and every one of those
     * steps refuses to happen twice. A published report card cannot be
     * regenerated (`GenerateReportCardsAction` skips it) and `report_cards`
     * is unique per student and term, so the second run of the walk against
     * the real term would find nothing to publish and fail on a rule working
     * as designed. It also cannot use Term 1, because `sensitiveRecords()`
     * plants a *published* card there on purpose for the own-data probe.
     *
     * So the walk gets `SMOKE-Term`: the same year, sorted last, and emptied
     * here on every run — its exams, marks, status audits, term grades, report
     * cards and the documents behind them. The term row itself is kept, since
     * enrolments may reference a term with `ON DELETE RESTRICT` (ADR-037).
     */
    private function examCycle(AcademicYear $year): void
    {
        $termId = (int) (DB::table('terms')
            ->where('academic_year_id', $year->id)
            ->where('name', 'SMOKE-Term')
            ->value('id')
            ?? DB::table('terms')->insertGetId([
                'academic_year_id' => $year->id, 'name' => 'SMOKE-Term', 'status' => 'upcoming',
                'start_date' => now()->subDays(30)->toDateString(), 'end_date' => now()->addDays(30)->toDateString(),
                'sort_order' => 99, 'created_at' => now(), 'updated_at' => now(),
            ]));

        $examIds = DB::table('exams')->where('name', 'SMOKE-Exam')->pluck('id');
        DB::table('exam_marks')->whereIn('exam_id', $examIds)->delete();
        DB::table('exam_status_audits')->whereIn('exam_id', $examIds)->delete();
        DB::table('exams')->whereIn('id', $examIds)->delete();

        $cardIds = DB::table('report_cards')->where('term_id', $termId)->pluck('id');
        DB::table('report_card_comments')->whereIn('report_card_id', $cardIds)->delete();
        DB::table('documents')->where('documentable_type', 'report_card')->whereIn('documentable_id', $cardIds)->delete();
        DB::table('report_cards')->whereIn('id', $cardIds)->delete();
        DB::table('term_grades')->where('term_id', $termId)->delete();
    }

    /**
     * The staff member's month, reset: the walk in `scripts/smoke/hr.mjs`.
     *
     * The staff actor is the seeded teacher (`teacher@akuru.edu.mv`): a login
     * the other walks already use, with a `teachers` row so an approved leave
     * also produces the teacher absence. Everything the walk needs is planted
     * here — a contract, an annual entitlement, a permit that expires in 25
     * days — and everything the last run left is removed: the leave request
     * and what its approval wrote (ledger, on-leave rows, teacher absence,
     * substitution suggestions), today's self check-in, the appraisal cycle,
     * the expiry notices, and payroll period 2099-12 with its payslips.
     *
     * Two settings are switched on for the walk. Self check-in has no screen
     * of its own yet (S5 audit D3) and defaults off, so a walk that checks in
     * needs the row set. `payroll.enabled` is only half the flag — the
     * environment's `PAYROLL_ENABLED` is the other half and stays off on
     * every host until two parallel cycles match — so setting the row here
     * enables nothing on its own; the walk says so when payroll is off.
     */
    private function hrCycle(AcademicYear $year, ?object $admin): void
    {
        $userId = (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');
        if ($userId <= 0) {
            $this->command?->warn('No teacher@ account — the HR-cycle markers were skipped.');

            return;
        }

        $profileId = (int) (DB::table('staff_profiles')->where('user_id', $userId)->value('id')
            ?? DB::table('staff_profiles')->insertGetId([
                'user_id' => $userId, 'first_name' => 'Smoke', 'last_name' => 'Marker', 'gender' => 'female',
                'joined_date' => '2026-01-01', 'employment_type' => 'full_time', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]));

        // What the walk needs to exist.
        if (! DB::table('staff_contracts')->where('staff_profile_id', $profileId)->where('status', 'active')->exists()) {
            DB::table('staff_contracts')->insert([
                'staff_profile_id' => $profileId, 'contract_type' => 'permanent',
                'start_date' => '2026-01-01', 'basic_salary' => 9876, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->plantEntitlement($profileId, $year->id);
        $permitIds = DB::table('documents')->where('title', 'SMOKE-Permit')->pluck('id');
        DB::table('document_expiry_notices')->whereIn('document_id', $permitIds)->delete();
        DB::table('documents')->whereIn('id', $permitIds)->delete();
        DB::table('documents')->insert([
            'documentable_type' => 'staff_profile', 'documentable_id' => $profileId,
            'media_path' => 'smoke/permit.pdf', 'document_type' => 'passport', 'title' => 'SMOKE-Permit',
            'expires_at' => now()->addDays(25)->toDateString(), 'uploaded_by' => $admin?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // What the last run left behind.
        $requestIds = DB::table('requests')->where('reason', 'SMOKE-Leave')->pluck('id');
        DB::table('leave_ledger')->whereIn('request_id', $requestIds)->delete();
        foreach ($requestIds as $requestId) {
            DB::table('substitution_requests')->where('notes', 'like', '%request #'.$requestId)->delete();
        }
        DB::table('teacher_absences')->where('reason', 'SMOKE-Leave')->delete();
        DB::table('requests')->whereIn('id', $requestIds)->delete();
        DB::table('staff_attendance')->where('staff_profile_id', $profileId)
            ->where('status', 'on_leave')->whereDate('date', '>', now()->toDateString())->delete();
        DB::table('staff_attendance')->where('staff_profile_id', $profileId)
            ->whereDate('date', now()->toDateString())->where('source', 'self')->delete();
        DB::table('appraisal_cycles')->where('name', 'SMOKE-Cycle')->delete();

        $periodId = (int) DB::table('payroll_periods')->where('year', 2099)->where('month', 12)->value('id');
        if ($periodId > 0) {
            $payslipIds = DB::table('payslips')->where('payroll_period_id', $periodId)->pluck('id');
            DB::table('documents')->where('documentable_type', 'payslip')->whereIn('documentable_id', $payslipIds)->delete();
            DB::table('payslips')->whereIn('id', $payslipIds)->delete();
            DB::table('payroll_postings')->where('year', 2099)->where('month', 12)->delete();
            DB::table('payroll_periods')->where('id', $periodId)->delete();
        }

        foreach (['hr.staff_self_checkin' => 'hr', 'payroll.enabled' => 'payroll'] as $key => $group) {
            DB::table('settings')->updateOrInsert(['key' => $key], [
                'value' => '1', 'type' => 'boolean', 'group' => $group, 'label' => $key,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * `scripts/smoke/author.mjs` builds `SMOKE-Authored` through the screens
     * — course, module, lesson, three blocks, a revision, an offering, an
     * enrolment, progress — so this plants nothing and only clears what the
     * last run left, in foreign-key order, so the walk can run again. The
     * course is removed outright rather than kept like `SMOKE-Course`: it is
     * created by the walk, so nothing else can point at it.
     */
    private function authorCycle(): void
    {
        $courseIds = DB::table('courses')->where('title', 'SMOKE-Authored')->pluck('id');
        if ($courseIds->isEmpty()) {
            return;
        }

        $lessonIds = DB::table('lessons')->whereIn('course_id', $courseIds)->pluck('id');
        $enrollmentIds = DB::table('course_enrollments')->whereIn('course_id', $courseIds)->pluck('id');

        DB::table('activity_attempts')->whereIn('course_id', $courseIds)->delete();
        DB::table('activities')->whereIn('course_id', $courseIds)->delete();
        $assessmentIds = DB::table('assessments')->whereIn('course_id', $courseIds)->pluck('id');
        DB::table('assessment_attempts')->whereIn('assessment_id', $assessmentIds)->delete();
        DB::table('assessment_questions')->whereIn('assessment_id', $assessmentIds)->delete();
        DB::table('assessments')->whereIn('id', $assessmentIds)->delete();
        DB::table('student_lesson_progress')->whereIn('enrollment_id', $enrollmentIds)->delete();
        DB::table('course_enrollments')->whereIn('id', $enrollmentIds)->delete();
        DB::table('content_blocks')->whereIn('course_id', $courseIds)->delete();
        DB::table('lessons')->whereIn('id', $lessonIds)->update(['current_revision_id' => null]);
        DB::table('lesson_revisions')->whereIn('lesson_id', $lessonIds)->delete();
        DB::table('lessons')->whereIn('id', $lessonIds)->delete();
        DB::table('course_modules')->whereIn('course_id', $courseIds)->delete();
        DB::table('course_offerings')->whereIn('course_id', $courseIds)->delete();
        DB::table('course_review_decisions')->whereIn('course_id', $courseIds)->delete();
        DB::table('course_instructor')->whereIn('course_id', $courseIds)->delete();
        DB::table('courses')->whereIn('id', $courseIds)->delete();

        // The image the walk uploaded, file and row.
        foreach (DB::table('media_files')->where('original_name', 'smoke-authored.png')->get(['id', 'disk', 'path']) as $media) {
            Storage::disk($media->disk)->delete($media->path);
            DB::table('media_files')->where('id', $media->id)->delete();
        }
    }

    /**
     * `scripts/smoke/intake.mjs` creates a scheduled offering (`SMOKE-Intake`)
     * for a published course through the screens, a session on it, and has
     * the student enrol into it and the office mark them present. This keeps
     * the course — `SMOKE-Intake-Course`, kept and updated like `SMOKE-Course`
     * for the same foreign-key reasons — and clears what a run leaves: the
     * student's enrolment on it, the offering, its sessions and attendance.
     */
    private function intakeCycle(): void
    {
        $courseId = (int) DB::table('courses')->where('slug', 'smoke-intake-course')->value('id');
        $course = [
            'course_category_id' => DB::table('course_categories')->orderBy('id')->value('id'),
            'title' => 'SMOKE-Intake-Course',
            'short_desc' => 'Planted by SmokeMarkerSeeder for the intake walk.',
            'body' => 'Planted by SmokeMarkerSeeder.',
            'cover_image' => '',
            'status' => 'open',
            'workflow_status' => 'published',
            'fee' => 0,
            'registration_fee_amount' => 0,
            'updated_at' => now(),
        ];
        if ($courseId > 0) {
            DB::table('courses')->where('id', $courseId)->update($course);
        } else {
            $courseId = DB::table('courses')->insertGetId($course + ['slug' => 'smoke-intake-course', 'created_at' => now()]);
        }

        // What the last run left, in foreign-key order.
        $offeringIds = DB::table('course_offerings')->where('course_id', $courseId)->where('title', 'SMOKE-Intake')->pluck('id');
        $enrollmentIds = DB::table('course_enrollments')->where('course_id', $courseId)->pluck('id');
        DB::table('attendance_records')->whereIn('course_offering_id', $offeringIds)->delete();
        DB::table('course_offering_sessions')->whereIn('course_offering_id', $offeringIds)->delete();
        DB::table('offering_repin_events')->whereIn('course_offering_id', $offeringIds)->delete();
        DB::table('student_lesson_progress')->whereIn('enrollment_id', $enrollmentIds)->delete();
        DB::table('course_enrollments')->whereIn('id', $enrollmentIds)->delete();
        DB::table('course_offerings')->whereIn('id', $offeringIds)->delete();
    }

    /**
     * `scripts/smoke/assess.mjs` writes two bank questions (`SMOKE-Q1`,
     * `SMOKE-Q2`), builds `SMOKE-Assessment` on `SMOKE-Course` and has the
     * student sit it. This plants nothing and clears what a run left, in
     * foreign-key order: attempts, the pivot, the assessment, the questions.
     */
    private function assessCycle(): void
    {
        $assessmentIds = DB::table('assessments')->where('title', 'SMOKE-Assessment')->pluck('id');
        $questionIds = DB::table('questions')->where('question_text', 'like', 'SMOKE-Q%')->pluck('id');

        DB::table('assessment_attempts')->whereIn('assessment_id', $assessmentIds)->delete();
        DB::table('assessment_questions')->whereIn('assessment_id', $assessmentIds)->orWhereIn('question_id', $questionIds)->delete();
        DB::table('assessments')->whereIn('id', $assessmentIds)->delete();
        DB::table('questions')->whereIn('id', $questionIds)->delete();
    }

    /**
     * `scripts/smoke/certify.mjs` builds the `SMOKE-Cert` template, issues a
     * certificate on it to the seeded student and revokes it. This plants
     * nothing and clears what a run left: the issued rows, their rendered
     * documents, and the template.
     */
    private function certifyCycle(): void
    {
        $templateIds = DB::table('certificate_templates')->where('name', 'SMOKE-Cert')->pluck('id');
        $issuedIds = DB::table('issued_certificates')->whereIn('certificate_template_id', $templateIds)->pluck('id');

        foreach (DB::table('documents')->where('documentable_type', 'issued_certificate')->whereIn('documentable_id', $issuedIds)->get(['id', 'media_path']) as $document) {
            Storage::disk('local')->delete($document->media_path);
            DB::table('documents')->where('id', $document->id)->delete();
        }
        DB::table('issued_certificates')->whereIn('id', $issuedIds)->delete();
        DB::table('certificate_templates')->whereIn('id', $templateIds)->delete();
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

        $this->plantEntitlement($staff->id, $year->id);
    }

    /**
     * 333 entitled days is not a number any real policy produces, which is
     * the point — the balances screen either shows it or it does not.
     *
     * The balance is a ledger sum, not the entitlement column
     * (`LeaveBalanceCalculator`), so an entitlement planted without its
     * opening `entitled` ledger row shows 333 entitled and 0 to take — which
     * is how the first HR walk got "Insufficient leave balance (0 remaining)".
     */
    private function plantEntitlement(int $profileId, int $yearId): void
    {
        $typeId = (int) DB::table('leave_types')->where('code', 'annual')->value('id');
        if ($typeId <= 0) {
            return;
        }

        DB::table('leave_entitlements')->updateOrInsert(
            ['staff_profile_id' => $profileId, 'leave_type_id' => $typeId, 'academic_year_id' => $yearId],
            ['entitled_days' => 333, 'created_at' => now(), 'updated_at' => now()]
        );
        $entitlementId = (int) DB::table('leave_entitlements')
            ->where('staff_profile_id', $profileId)->where('leave_type_id', $typeId)->where('academic_year_id', $yearId)
            ->value('id');

        DB::table('leave_ledger')->where('entitlement_id', $entitlementId)->where('reason', 'entitled')->delete();
        DB::table('leave_ledger')->insert([
            'entitlement_id' => $entitlementId, 'request_id' => null, 'days' => 333, 'reason' => 'entitled',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
