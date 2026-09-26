<?php

namespace Database\Seeders;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Models\Lesson;
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

        $admin = DB::table('users')->where('email', 'admin@akuru.edu.mv')->first();

        // The pupil the walks sign in as (student@'s), and their class — not
        // the first class of the year and its first pupil, which are the same
        // people on a fresh seed and not on a host with history (STATUS §5fz).
        $loginStudentId = (int) DB::table('students')
            ->where('user_id', (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))
            ->value('id');
        $loginClassId = $loginStudentId > 0
            ? (int) DB::table('class_student')->where('student_id', $loginStudentId)->where('status', 'active')
                ->where('academic_year_id', $year->id)->orderBy('id')->value('class_id')
            : 0;
        $class = ($loginClassId > 0 ? ClassRoom::query()->find($loginClassId) : null)
            ?? ClassRoom::query()->where('academic_year_id', $year->id)->first();
        $studentId = $loginStudentId > 0
            ? $loginStudentId
            : (int) DB::table('class_student')->where('class_id', $class?->id)->value('student_id');

        // The staff member the walks sign in as (teacher@'s profile) when it
        // exists. Staging's first seeder run, with no teacher@ yet, had made a
        // profile with no user behind it, and `first()` kept finding that one:
        // the attendance, the appraisal and the balance the hr walk read were
        // somebody else's "Smoke Marker" (§5fz). That orphan is the seeder's
        // own residue, and it is removed here.
        $teacherUserId = (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');
        // The residue is renamed rather than deleted: its rows (attendance,
        // appraisals) may be referenced, and "Smoke Stand-in" is no longer the
        // name the hr walk looks up.
        DB::table('staff_profiles')->where('first_name', 'Smoke')->where('last_name', 'Marker')
            ->where(fn ($query) => $teacherUserId > 0 ? $query->where('user_id', '!=', $teacherUserId) : $query->whereRaw('1 = 1'))
            ->update(['last_name' => 'Stand-in', 'updated_at' => now()]);
        $staff = ($teacherUserId > 0 ? StaffProfile::query()->where('user_id', $teacherUserId)->first() : null)
            ?? StaffProfile::query()->first();

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
        $this->arabicCycle();
        $this->quranCycle();
        $this->hifzCycle();
        $this->readerCycle();
        $this->familyCycle();
        $this->signupCycle();
        $this->schoolDayCycle();
        $this->timetableCycle($year);
        $this->consentCycle($studentId, $admin);
        $this->requestsCycle($class);
        $this->vendorCycle();

        // A default `migrate:fresh --seed` leaves `staff_profiles` empty, and
        // this used to skip the whole HR block in silence — so the sweep
        // afterwards reported five HR screens as not showing their rows, and
        // the screens were fine. A seeder that quietly plants nothing makes the
        // thing it is checking look broken, which is the worst direction for
        // the error to run.
        // `hrCycle()` may have just made teacher@'s profile on an empty table,
        // so look again before making one (user_id is unique).
        $staff = ($teacherUserId > 0 ? StaffProfile::query()->where('user_id', $teacherUserId)->first() : null)
            ?? $staff
            ?? StaffProfile::query()->first()
            ?? $this->makeStaffProfile();

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
        // teacher@ only. This used to fall back to *any* user, and on a host
        // seeded before teacher@ existed it made "Smoke Marker" out of admin@
        // — a second Smoke Marker that every name lookup found first (§5fz).
        // No teacher@ means no HR markers, and the warning below says so.
        $userId = DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');

        if ($userId === null || StaffProfile::query()->where('user_id', $userId)->exists()) {
            return $userId === null ? null : StaffProfile::query()->where('user_id', $userId)->first();
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
        // Take the date over rather than insert beside it: `calendar_days` is
        // unique on (date, year), and on a host with history the day three
        // out is often already taken — `create-sweep.mjs` writes the day after
        // the last entry, so a marker that moves forward with the calendar
        // lands on it by the fourth seeding. Staging's fourth seed died here
        // on a 1062 (STATUS §5fz).
        DB::table('calendar_days')->updateOrInsert(
            ['academic_year_id' => $year->id, 'date' => now()->addDays(3)->toDateString()],
            [
                'type' => 'holiday', 'title' => 'SMOKE-Holiday', 'affects_timetable' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        );
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

        // `quran.mjs` adds a session to this offering and maps it onto a halaqa
        // session, so the offering's children go first, in foreign-key order.
        $offeringIds = DB::table('course_offerings')->where('title', 'SMOKE-Offering')->pluck('id');
        $sessionIds = DB::table('course_offering_sessions')->whereIn('course_offering_id', $offeringIds)->pluck('id');
        DB::table('attendance_records')->whereIn('course_offering_id', $offeringIds)->delete();
        DB::table('offering_halaqa_session_links')->whereIn('course_offering_session_id', $sessionIds)->delete();
        DB::table('course_offering_sessions')->whereIn('id', $sessionIds)->delete();
        DB::table('course_offerings')->whereIn('id', $offeringIds)->delete();

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

        // Enrolled on the People pupil alone. This used to pair the pupil
        // with a legacy `registration_students` row first, because
        // `course_enrollments.student_id` was NOT NULL; Deploy 3 made it
        // optional (slice 1) and stopped writing it (slice 2).
        DB::table('course_enrollments')->insert([
            'course_id' => $courseId,
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

    /**
     * A real source, not a marker: `source` casts to `ConsentSource`, and the
     * `SMOKE-Source` this used to plant made the pupil's Consents tab a
     * `ValueError` 500 on every seeded database from 2026-09-14 until the
     * consent walk opened it (STATUS §5fv) — the sweep only ever read the
     * directory. `admission_form` is what an enrolment-time consent would
     * say, and it is not the `admin` the walk writes, so the cycle can tell
     * the two apart.
     */
    private function consent(int $studentId, ?object $admin): void
    {
        DB::table('consents')->where('source', 'SMOKE-Source')->delete();
        DB::table('consents')->where('person_type', 'student')->where('person_id', $studentId)
            ->where('consent_type', 'photo_media_use')->where('source', 'admission_form')->delete();
        DB::table('consents')->insert([
            'person_type' => 'student', 'person_id' => $studentId,
            'consent_type' => 'photo_media_use', 'granted' => 1,
            'granted_by' => $admin?->id, 'granted_at' => now(), 'source' => 'admission_form',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * `scripts/smoke/consent.mjs` records a marketing consent, revokes it,
     * revokes the photo consent and grants it back — every one an `admin`
     * row on the pupil. The gate reads the newest row, so a revoke left
     * behind would outrank the `SMOKE-Source` grant `consent()` re-plants;
     * this clears the office's rows for the two types the walk touches, and
     * plants the photo document the public achievements page offers when
     * the consent stands (`ListPublicAchievementsAction`).
     */
    private function consentCycle(int $studentId, ?object $admin): void
    {
        DB::table('consents')->where('person_type', 'student')->where('person_id', $studentId)
            ->whereIn('consent_type', ['photo_media_use', 'marketing_messages'])
            ->where('source', 'admin')->delete();

        DB::table('documents')->where('title', 'SMOKE-Photo')->delete();
        DB::table('documents')->insert([
            'documentable_type' => 'student', 'documentable_id' => $studentId,
            'media_path' => 'smoke/photo.jpg', 'document_type' => 'photo', 'title' => 'SMOKE-Photo',
            'uploaded_by' => $admin?->id, 'created_at' => now(), 'updated_at' => now(),
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

    /**
     * `scripts/smoke/arabic.mjs` adds a letter to the Arabic reference,
     * builds a reading activity tagged with it on `SMOKE-Course`, has the
     * student answer it, and reads both skill reports. This plants nothing
     * and clears what a run left: the attempts, the activity, the letter.
     */
    private function arabicCycle(): void
    {
        $activityIds = DB::table('activities')->where('title', 'SMOKE-Arabic-Activity')->pluck('id');
        DB::table('activity_attempts')->whereIn('activity_id', $activityIds)->delete();
        DB::table('activities')->whereIn('id', $activityIds)->delete();
        DB::table('arabic_letters')->where('key_name', 'smoke_letter')->delete();
    }

    /**
     * `quran.mjs` builds a recitation activity on `SMOKE-Course`, has the
     * student hand it in and the marker score it, and links `SMOKE-Offering`
     * to a Hifz program (Qur'an A.3). The program is this seeder's own —
     * `SMOKE-Halaqa`, one session dated today — because a host without the
     * demo Hifz data has no program to link, and the link picker is disabled
     * with none. It is a legacy-table row on a synthetic host only:
     * `halaqa:verify-structure` will list it as unmapped, which is correct,
     * and the next seed replaces it. Session links carry the Hifz id without a
     * foreign key (ADR-019), so they are cleared before the program is.
     */
    private function quranCycle(): void
    {
        $activityIds = DB::table('activities')->where('title', 'SMOKE-Recite-Activity')->pluck('id');
        DB::table('activity_attempts')->whereIn('activity_id', $activityIds)->delete();
        DB::table('activities')->whereIn('id', $activityIds)->delete();

        $programIds = DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->pluck('id');
        $sessionIds = DB::table('hifz_sessions')->whereIn('hifz_program_id', $programIds)->pluck('id');
        DB::table('offering_halaqa_session_links')->whereIn('hifz_session_id', $sessionIds)->delete();
        DB::table('offering_halaqa_links')->whereIn('hifz_program_id', $programIds)->delete();
        DB::table('hifz_sessions')->whereIn('id', $sessionIds)->delete();
        DB::table('hifz_programs')->whereIn('id', $programIds)->delete();

        $teacherId = DB::table('teachers')->orderBy('id')->value('id');
        if ($teacherId === null) {
            return;
        }

        $programId = DB::table('hifz_programs')->insertGetId([
            'name' => 'SMOKE-Halaqa', 'description' => 'Planted by SmokeMarkerSeeder.', 'status' => 'active',
            'default_teacher_id' => $teacherId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hifz_sessions')->insert([
            'hifz_program_id' => $programId, 'teacher_id' => $teacherId, 'session_date' => now()->toDateString(),
            'title' => 'SMOKE-Halaqa-Session', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * `hifz.mjs` walks what survives of the Blade Hifz app (ADR-029): the
     * dean enrols the smoke pupil in `SMOKE-Halaqa`, the supervisor reviews
     * and the dean approves a milestone, and each role's dashboard shows
     * its share. The programme is `quranCycle()`'s, re-planted a moment
     * ago with nothing enrolled; this gives it the supervisor and teacher
     * the walk signs in as, and one *pending* milestone for the pupil —
     * recommendation itself moved to the engine board (`/teach/milestones`,
     * F5-P3), so the Blade half of the workflow starts from a row that
     * already exists.
     */
    private function hifzCycle(): void
    {
        $programId = DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->value('id');
        $studentId = DB::table('students')->where('user_id', DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))->value('id');
        if ($programId === null || $studentId === null) {
            return;
        }

        $teacherUserId = DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');
        $teacherId = DB::table('teachers')->where('user_id', $teacherUserId)->value('id') ?? DB::table('teachers')->orderBy('id')->value('id');
        DB::table('hifz_programs')->where('id', $programId)->update([
            'supervisor_id' => DB::table('users')->where('email', 'supervisor@akuru.edu.mv')->value('id'),
            'default_teacher_id' => $teacherId,
        ]);

        DB::table('hifz_milestones')->where('title', 'SMOKE-Milestone')->delete();
        DB::table('hifz_milestones')->insert([
            'hifz_program_id' => $programId, 'student_id' => $studentId, 'teacher_id' => $teacherId,
            'type' => 'surah_completed', 'surah_number' => 112, 'title' => 'SMOKE-Milestone', 'status' => 'pending',
            'completed_at' => now(), 'recommended_by' => $teacherUserId, 'recommended_at' => now(), 'created_by' => $teacherUserId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * BOOKSHOP_PLAN B1a and docs/vendors/FITRAH.md: staging carries Fitrah,
     * the first vendor, so `vendor.mjs` walks a real shop. Its owner here is
     * a synthetic staging login (`vendor@akuru.edu.mv`, the seeded password)
     * — never the real owner's email, which only production's office screen
     * ever uses. A second shop, `SMOKE-Other Shop`, holds one product the
     * walk must never see from Fitrah's side.
     *
     * Each run: Fitrah's agreement is un-accepted (the walk accepts it), the
     * walk's own products and invited vendors go, and the three sample
     * products are put back as they were.
     */
    private function vendorCycle(): void
    {
        $this->call(BookshopCatalogueSeeder::class);
        $this->call(BookshopPolicyPagesSeeder::class);

        $ownerId = $this->vendorLogin('vendor@akuru.edu.mv', 'Fitrah Owner (staging)');
        $otherOwnerId = $this->vendorLogin('vendor-other@akuru.edu.mv', 'Other Shop Owner (staging)');

        // What the walk made: vendors it invited, products it listed, their photos.
        $invited = DB::table('vendors')->where('name', 'like', 'SMOKE-Invited%')->pluck('id');
        $walkProducts = DB::table('products')->where('title', 'like', 'SMOKE-Walk%')->orWhereIn('vendor_id', $invited)->pluck('id');
        foreach (DB::table('product_images')->whereIn('product_id', $walkProducts)->pluck('media_file_id') as $mediaId) {
            $media = DB::table('media_files')->where('id', $mediaId)->first(['disk', 'path']);
            if ($media !== null) {
                Storage::disk($media->disk)->delete($media->path);
            }
            DB::table('product_images')->where('media_file_id', $mediaId)->delete();
            DB::table('media_files')->where('id', $mediaId)->delete();
        }
        // B2 (`checkout.mjs`): what the checkout walk made — its carts,
        // checkouts, orders, slips, reservations and saved addresses for
        // the walk's own people, and Fitrah's delivery methods (the walk
        // sets them from the template). Money stays: the wallet ledger is
        // append-only (rule 12), so the walk's wallet payment is a real
        // debit and the top-up below brings the balance back.
        $walkPeople = DB::table('users')->whereIn('email', ['student@akuru.edu.mv', 'vendor@akuru.edu.mv'])->pluck('id');
        $walkCheckouts = DB::table('bookshop_checkouts')->whereIn('user_id', $walkPeople)->pluck('id');
        foreach (DB::table('bank_transfer_slips')->whereIn('bookshop_checkout_id', $walkCheckouts)->pluck('media_file_id') as $mediaId) {
            $media = DB::table('media_files')->where('id', $mediaId)->first(['disk', 'path']);
            if ($media !== null) {
                Storage::disk($media->disk)->delete($media->path);
            }
            DB::table('bank_transfer_slips')->where('media_file_id', $mediaId)->delete();
            DB::table('media_files')->where('id', $mediaId)->delete();
        }
        $walkOrders = DB::table('orders')->whereIn('bookshop_checkout_id', $walkCheckouts)->pluck('id');
        // B3 (`fulfilment.mjs`): the order threads the walk wrote on, its
        // returns and refund rows. Wallet credits from refunds are money and
        // stay; the top-up below evens the balance out.
        $walkThreads = DB::table('message_threads')->where('context_type', 'order')->whereIn('context_id', $walkOrders)->pluck('id');
        DB::table('messages')->whereIn('thread_id', $walkThreads)->delete();
        DB::table('message_participants')->whereIn('message_thread_id', $walkThreads)->delete();
        DB::table('message_threads')->whereIn('id', $walkThreads)->delete();
        DB::table('order_refunds')->whereIn('order_id', $walkOrders)->delete();
        DB::table('order_returns')->whereIn('order_id', $walkOrders)->delete();
        DB::table('order_events')->whereIn('order_id', $walkOrders)->delete();
        DB::table('order_items')->whereIn('order_id', $walkOrders)->delete();
        DB::table('orders')->whereIn('id', $walkOrders)->delete();
        DB::table('stock_reservations')->whereIn('bookshop_checkout_id', $walkCheckouts)->delete();
        DB::table('bookshop_checkouts')->whereIn('id', $walkCheckouts)->delete();
        DB::table('cart_items')->whereIn('cart_id', DB::table('carts')->whereIn('user_id', $walkPeople)->pluck('id'))->delete();
        DB::table('carts')->whereIn('user_id', $walkPeople)->delete();
        DB::table('customer_addresses')->whereIn('user_id', $walkPeople)->delete();
        DB::table('vendor_delivery_methods')->whereIn('vendor_id', DB::table('vendors')->where('slug', 'fitrah')->pluck('id'))->delete();

        DB::table('products')->whereIn('id', $walkProducts)->delete();
        DB::table('vendors')->whereIn('id', $invited)->delete();

        $fitrahId = $this->smokeVendor('fitrah', 'FIT', [
            'name' => 'Fitrah',
            'tagline' => 'iman.noor.ihsan',
            'contact_email' => 'vendor@akuru.edu.mv',
            'contact_phone' => '7000000',
        ], $ownerId);
        $otherId = $this->smokeVendor('smoke-other-shop', 'SOS', ['name' => 'SMOKE-Other Shop'], $otherOwnerId);

        // The walk accepts the agreement itself, every run, and the staff it
        // added last time leave the shop (their accounts stay: people are not
        // the walk's to delete).
        DB::table('vendor_members')->where('vendor_id', $fitrahId)->where('user_id', $ownerId)->update(['agreement_accepted_at' => null]);
        DB::table('vendor_members')->where('vendor_id', $fitrahId)
            ->whereIn('user_id', DB::table('users')->where('email', 'like', 'smoke-staff%')->pluck('id'))
            ->delete();
        DB::table('vendor_members')->where('vendor_id', $otherId)->update(['agreement_accepted_at' => now()]);

        $category = fn (string $slug) => DB::table('product_categories')->where('slug', $slug)->value('id');
        $this->smokeProduct($fitrahId, 'smoke-arabic-letters-tracing-book', 'Arabic Letters Tracing Book', 85, 'zero_rated', $category('workbooks'), 40, ['author' => 'Fitrah', 'pages' => '56', 'language' => 'Arabic', 'age_range' => '4–6']);
        $this->smokeProduct($fitrahId, 'smoke-wooden-alphabet-puzzle', 'Wooden Alphabet Puzzle', 240, 'standard', $category('educational-toys'), 12, ['age_range' => '3–6', 'subject' => 'Thaana letters']);
        $this->smokeProduct($fitrahId, 'smoke-kids-prayer-mat', 'Kids Prayer Mat', 180, 'standard', $category('islamic-studies'), 3, ['age_range' => '3–10'], lowStockAt: 5);
        $this->smokeProduct($otherId, 'smoke-other-secret', 'SMOKE-Other-Secret', 99, 'standard', null, 1, []);

        // B1b (`shop.mjs`): a photo on the tracing book, a Dhivehi title on
        // the puzzle, and a draft the public shop must never show.
        DB::table('products')->where('slug', 'smoke-wooden-alphabet-puzzle')->update(['title_dv' => 'ލަކުޑި އަކުރު ޕަޒަލް']);
        $this->smokeProduct($fitrahId, 'smoke-hidden-draft', 'SMOKE-Hidden-Draft', 10, 'standard', null, 1, []);
        DB::table('products')->where('slug', 'smoke-hidden-draft')->update(['status' => 'draft']);
        $this->smokeProductPhoto('smoke-arabic-letters-tracing-book', database_path('seeders/fixtures/vendors/fitrah-logo.jpg'));

        // B3: Fitrah open, with the standard seven-day window, every run.
        // B4: verified by the office (the walk checks the badge shows), not an
        // Akuru partner (the walk checks Akuru's palette stays locked).
        DB::table('vendors')->where('id', $fitrahId)->update([
            'holiday_from' => null, 'holiday_until' => null, 'holiday_notice' => null,
            'return_window_days' => null, 'return_conditions' => null,
            'badges' => json_encode(['verified']),
        ]);

        // B4 (`storefront.mjs`): the walk designs and publishes Fitrah's page
        // itself, so the storefront and its images start from nothing.
        $storefrontIds = DB::table('vendor_storefronts')->where('vendor_id', $fitrahId)->pluck('id');
        $storefrontMedia = [];
        foreach (DB::table('vendor_storefronts')->whereIn('id', $storefrontIds)->get(['draft_identity', 'published_identity']) as $row) {
            foreach ([$row->draft_identity, $row->published_identity] as $json) {
                $identity = json_decode((string) $json, true) ?: [];
                $storefrontMedia = [...$storefrontMedia, ...array_filter(array_values((array) ($identity['images'] ?? [])), 'is_numeric')];
            }
        }
        DB::table('vendor_storefront_versions')->whereIn('vendor_storefront_id', $storefrontIds)->delete();
        DB::table('vendor_storefronts')->whereIn('id', $storefrontIds)->delete();
        // B5 (`sections.mjs`): the walk's pages, collections and image library
        // (the library's media files go with the storefront's below).
        $storefrontMedia = [...$storefrontMedia, ...DB::table('vendor_storefront_images')->where('vendor_id', $fitrahId)->pluck('media_file_id')->all()];
        DB::table('vendor_storefront_images')->where('vendor_id', $fitrahId)->delete();
        DB::table('vendor_pages')->where('vendor_id', $fitrahId)->delete();
        DB::table('vendor_collection_products')->whereIn('vendor_collection_id', DB::table('vendor_collections')->where('vendor_id', $fitrahId)->pluck('id'))->delete();
        DB::table('vendor_collections')->where('vendor_id', $fitrahId)->delete();
        foreach (array_unique($storefrontMedia) as $mediaId) {
            $media = DB::table('media_files')->where('id', $mediaId)->first(['disk', 'path']);
            if ($media !== null) {
                $stem = preg_replace('/\.[^.]+$/', '', $media->path);
                $copies = array_filter(Storage::disk($media->disk)->files(dirname($media->path)), fn ($f) => str_starts_with($f, $stem.'-w'));
                Storage::disk($media->disk)->delete([$media->path, ...$copies]);
            }
            DB::table('media_files')->where('id', $mediaId)->delete();
        }

        // B2: the walk pays from the student's wallet; the stock the walk
        // bought last time is put back by the updateOrInsert above.
        $this->topUpWallet('student@akuru.edu.mv', 500.0, 'SMOKE-Wallet top-up so a customer can pay in the bookstore.');

        // B6 (`vendor-money.mjs`): the walk's payouts, the fake bank details it
        // enters and Fitrah's commission invoices go; earnings went with the
        // walk's orders above. Then one paid, delivered order from three
        // weeks ago whose earning has matured, so the walk has a balance to
        // ask for and a month to invoice. Never real bank details here.
        DB::table('vendor_earnings')->whereIn('vendor_id', [$fitrahId, $otherId])->whereNull('order_id')->delete();
        DB::table('vendor_payouts')->whereIn('vendor_id', [$fitrahId, $otherId])->delete();
        DB::table('vendor_bank_details')->whereIn('vendor_id', [$fitrahId, $otherId])->delete();
        DB::table('vendor_commission_invoices')->whereIn('vendor_id', [$fitrahId, $otherId])->delete();
        $this->smokeMaturedOrder($fitrahId, 'student@akuru.edu.mv', 'smoke-arabic-letters-tracing-book', 2, 30.0, now()->subDays(21));

        // B7 (`polish.mjs`): the walk's reviews went with the walk's orders
        // above (they cascade); the matured order just made is the delivered
        // one the student reviews. Its wishlist, back-in-stock notices, the
        // shop's own codes, the office's home picks, badges, ratings and the
        // free-delivery amount go too. A sold-out product to be told about.
        DB::table('wishlist_items')->whereIn('user_id', $walkPeople)->delete();
        DB::table('stock_alerts')->whereIn('user_id', $walkPeople)->delete();
        $vendorCodes = DB::table('discount_codes')->where('applies_to_type', 'vendor')->whereIn('applies_to_id', [$fitrahId, $otherId])->pluck('id');
        DB::table('discount_redemptions')->whereIn('discount_code_id', $vendorCodes)->delete();
        DB::table('discount_codes')->whereIn('id', $vendorCodes)->delete();
        $shopProducts = DB::table('products')->whereIn('vendor_id', [$fitrahId, $otherId])->pluck('id');
        DB::table('shop_home_features')->where('heading', 'like', 'SMOKE-%')->orWhereIn('product_id', $shopProducts)->delete();
        DB::table('products')->whereIn('id', $shopProducts)->update(['badge' => null, 'badge_dv' => null, 'badge_ar' => null, 'rating_avg' => null, 'rating_count' => 0]);
        DB::table('vendors')->whereIn('id', [$fitrahId, $otherId])->update(['free_delivery_over' => null]);
        $this->smokeProduct($fitrahId, 'smoke-quran-stand', 'Wooden Quran Stand', 150, 'standard', $category('islamic-studies'), 0, ['material' => 'Wood']);

        // B8 (`operations.mjs`): the walk's imported products are
        // `SMOKE-Walk …` and went with the walk's products above; its
        // duplicates (`…-copy`), the stock log of both shops, the low-stock
        // flags, the shop's notice choices and the office's switches go too,
        // so each run starts from the same stock and the default channels.
        DB::table('products')->whereIn('vendor_id', [$fitrahId, $otherId])->where('slug', 'like', '%-copy%')->delete();
        DB::table('stock_movements')->whereIn('vendor_id', [$fitrahId, $otherId])->delete();
        DB::table('products')->whereIn('vendor_id', [$fitrahId, $otherId])->update(['low_stock_notified_at' => null]);
        DB::table('vendors')->whereIn('id', [$fitrahId, $otherId])->update(['notice_settings' => null]);
        DB::table('settings')->where('key', 'like', 'bookshop_notices_%')->delete();

        // B9a (`apply.mjs`): the parent applies to open `SMOKE-Walk …` and
        // the office approves. Their applications, the shop that approval
        // made, and the vendor role it gave them go, and the form is open
        // again (the office's switch back to its default).
        $applicant = DB::table('users')->where('email', 'parent@akuru.edu.mv')->value('id');
        if ($applicant !== null) {
            $madeShops = DB::table('vendor_applications')->where('user_id', $applicant)->whereNotNull('vendor_id')->pluck('vendor_id');
            DB::table('vendor_applications')->where('user_id', $applicant)->delete();
            DB::table('vendor_members')->whereIn('vendor_id', $madeShops)->delete();
            DB::table('vendors')->whereIn('id', $madeShops)->where('slug', 'like', 'smoke-walk-%')->delete();
            $vendorRole = DB::table('roles')->where('name', 'vendor')->value('id');
            if ($vendorRole !== null && ! DB::table('vendor_members')->where('user_id', $applicant)->exists()) {
                DB::table('model_has_roles')->where('role_id', $vendorRole)->where('model_id', $applicant)->delete();
            }
        }
        DB::table('settings')->where('key', (string) config('bookshop.onboarding.setting_key'))->delete();

        // B9b (`cod.mjs`): the walk turns cash on delivery on for Fitrah and
        // the office turns it off; both go back. The walk's cash order went
        // with the student's orders above.
        DB::table('vendors')->whereIn('id', [$fitrahId, $otherId])->update(['cod_enabled' => false, 'cod_max' => null]);
        DB::table('settings')->where('key', (string) config('bookshop.cod.setting_key'))->delete();

        // B9c (`newsletter.mjs`): Fitrah's newsletter list starts empty (the
        // walk adds its Newsletter section; the storefront was reset above).
        // The parent — whom no other walk shops as — gets a cart left a day
        // and a half ago, and the reminder run once, so the walk finds it.
        DB::table('vendor_newsletter_subscribers')->whereIn('vendor_id', [$fitrahId, $otherId])->delete();
        if ($applicant !== null) {
            $parentCarts = DB::table('carts')->where('user_id', $applicant)->pluck('id');
            DB::table('cart_items')->whereIn('cart_id', $parentCarts)->delete();
            DB::table('carts')->whereIn('id', $parentCarts)->delete();
            DB::table('user_notifications')->where('user_id', $applicant)->where('title', __('shop.notice_cart_reminder_title', [], 'en'))->delete();
            $cartId = DB::table('carts')->insertGetId(['user_id' => $applicant, 'created_at' => now()->subHours(36), 'updated_at' => now()->subHours(36)]);
            DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => DB::table('products')->where('slug', 'smoke-wooden-alphabet-puzzle')->value('id'), 'quantity' => 1, 'created_at' => now()->subHours(36), 'updated_at' => now()->subHours(36)]);
            app(\App\Domains\Bookshop\Actions\Shop\RemindAbandonedCartsAction::class)->execute();
        }
    }

    /**
     * A paid, delivered order dated in the past, with its earning matured —
     * what B6's walk needs and no browser can make (the return window is
     * seven days). Through the real earning action, then backdated.
     */
    private function smokeMaturedOrder(int $vendorId, string $customerEmail, string $productSlug, int $quantity, float $deliveryFee, \Carbon\CarbonInterface $paidAt): void
    {
        $customerId = (int) DB::table('users')->where('email', $customerEmail)->value('id');
        $product = DB::table('products')->where('slug', $productSlug)->first(['id', 'title', 'price', 'sku', 'tax_class']);
        if ($customerId === 0 || $product === null) {
            return;
        }
        $goods = round((float) $product->price * $quantity, 2);
        $address = ['recipient_name' => 'SMOKE Customer', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Smoke Villa'];
        $number = 'SMK-'.$paidAt->format('ymd');
        $checkout = \App\Domains\Bookshop\Models\BookshopCheckout::query()->create([
            'number' => $number, 'user_id' => $customerId, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => $address,
            'subtotal' => $goods, 'discount' => 0, 'delivery_total' => $deliveryFee, 'total' => $goods + $deliveryFee, 'currency' => 'MVR', 'paid_at' => $paidAt,
        ]);
        $order = \App\Domains\Bookshop\Models\Order::query()->create([
            'number' => $number.'-FIT', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendorId, 'user_id' => $customerId, 'status' => 'delivered',
            'delivery_kind' => 'courier_male', 'delivery_name' => 'Delivery in Malé', 'delivery_fee' => $deliveryFee, 'delivery_carrier_paid' => false, 'delivery_handling_days' => 2,
            'address_snapshot' => $address, 'subtotal' => $goods, 'discount' => 0, 'tax' => 0, 'total' => $goods + $deliveryFee, 'currency' => 'MVR', 'tax_shown' => false,
            'paid_at' => $paidAt, 'processing_at' => $paidAt->copy()->addHours(2), 'dispatched_at' => $paidAt->copy()->addDay(), 'delivered_at' => $paidAt->copy()->addDays(2),
        ]);
        \App\Domains\Bookshop\Models\OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id, 'title' => $product->title, 'sku' => $product->sku, 'unit_price' => $product->price,
            'quantity' => $quantity, 'line_total' => $goods, 'tax_class' => $product->tax_class, 'tax_amount' => 0,
        ]);
        foreach (['placed' => $paidAt, 'paid' => $paidAt, 'processing' => $order->processing_at, 'dispatched' => $order->dispatched_at, 'delivered' => $order->delivered_at] as $type => $at) {
            \App\Domains\Bookshop\Models\OrderEvent::query()->create(['order_id' => $order->id, 'type' => $type, 'created_at' => $at]);
        }
        app(\App\Domains\Bookshop\Actions\Money\RecordVendorEarningAction::class)->execute($order->refresh());
        app(\App\Domains\Bookshop\Actions\Money\MatureVendorEarningsAction::class)->execute($vendorId);
    }

    /** One photo on a staging product, replaced on every run. */
    private function smokeProductPhoto(string $slug, string $path): void
    {
        $productId = (int) DB::table('products')->where('slug', $slug)->value('id');
        if ($productId === 0 || ! is_file($path)) {
            return;
        }

        foreach (DB::table('product_images')->where('product_id', $productId)->pluck('media_file_id') as $mediaId) {
            $media = DB::table('media_files')->where('id', $mediaId)->first(['disk', 'path']);
            if ($media !== null) {
                // The original and the resized copies the shop made of it.
                $stem = preg_replace('/\.[^.]+$/', '', $media->path);
                $copies = array_filter(Storage::disk($media->disk)->files(dirname($media->path)), fn ($f) => str_starts_with($f, $stem.'-w'));
                Storage::disk($media->disk)->delete([$media->path, ...$copies]);
            }
            DB::table('product_images')->where('media_file_id', $mediaId)->delete();
            DB::table('media_files')->where('id', $mediaId)->delete();
        }

        $stored = app(\App\Domains\Media\Actions\StorePublicMediaAction::class)->execute(
            new \Illuminate\Http\UploadedFile($path, basename($path), 'image/jpeg', null, true),
            null,
            ['image/jpeg'],
            ['smoke' => true],
            'shop-products',
        );
        DB::table('product_images')->insert([
            'product_id' => $productId, 'media_file_id' => $stored['id'], 'alt_text' => 'Arabic Letters Tracing Book',
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function vendorLogin(string $email, string $name): int
    {
        $id = (int) DB::table('users')->where('email', $email)->value('id');
        if ($id === 0) {
            $id = (int) app(\App\Domains\Identity\Actions\CreateUserAction::class)->execute($name, $email, 'password', null, 'vendor')['id'];
        }
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->find($id);
        if ($user !== null && ! $user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function smokeVendor(string $slug, string $code, array $attributes, int $ownerId): int
    {
        DB::table('vendors')->updateOrInsert(['slug' => $slug], $attributes + [
            'code' => $code, 'status' => 'active', 'commission_rate' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vendorId = (int) DB::table('vendors')->where('slug', $slug)->value('id');
        DB::table('vendor_members')->updateOrInsert(
            ['vendor_id' => $vendorId, 'user_id' => $ownerId],
            ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        );

        return $vendorId;
    }

    /**
     * @param  array<string, string>  $details
     */
    private function smokeProduct(int $vendorId, string $slug, string $title, float $price, string $taxClass, ?int $categoryId, int $stock, array $details, ?int $lowStockAt = null): void
    {
        DB::table('products')->updateOrInsert(['slug' => $slug], [
            'vendor_id' => $vendorId,
            'product_category_id' => $categoryId,
            'title' => $title,
            'summary' => 'Staging sample for the vendor walk.',
            'price' => $price,
            'currency' => 'MVR',
            'tax_class' => $taxClass,
            'sku' => strtoupper(substr(str_replace('smoke-', '', $slug), 0, 20)),
            'track_stock' => true,
            'stock' => $stock,
            'low_stock_at' => $lowStockAt,
            'status' => 'active',
            'visibility' => 'shop',
            'featured' => false,
            'tags' => json_encode(['staging']),
            'details' => json_encode($details),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * `reader.mjs` has the student read `SMOKE-Primer` — a published,
     * sign-in-only book of three pages — bookmark a page, and find it on
     * their My Library; and redeem a gift card the office issues. The book
     * is planted through the Library's own save and publish Actions, because
     * the page split happens at save time and nothing else knows the rule.
     * Reading progress and bookmarks are the walk's residue and go; the
     * gift card and its wallet credit are money and stay (rule 12 — the
     * ledger is append-only, and each run issues a fresh card).
     */
    private function readerCycle(): void
    {
        // `smoke-primer-upload` is what the walk's own upload step makes.
        $itemIds = DB::table('library_items')
            ->where(fn ($q) => $q->whereIn('slug', ['smoke-primer', 'smoke-primer-pdf'])->orWhere('slug', 'like', 'smoke-primer-upload%'))
            ->pluck('id');
        DB::table('library_bookmarks')->whereIn('library_item_id', $itemIds)->delete();
        DB::table('library_reading_progress')->whereIn('library_item_id', $itemIds)->delete();
        DB::table('library_reading_events')->whereIn('library_item_id', $itemIds)->delete();
        DB::table('library_item_pages')->whereIn('library_item_id', $itemIds)->delete();
        DB::table('library_items')->whereIn('id', $itemIds)->delete();

        $approverId = DB::table('users')->where('email', 'admin@akuru.edu.mv')->value('id') ?? DB::table('users')->orderBy('id')->value('id');
        if ($approverId === null) {
            return;
        }

        // The plan's required pages: real content, idempotent, and what the
        // reader walk's policy links open. Pages the office edited are kept.
        $this->call(LibraryPolicyPagesSeeder::class);

        $item = app(\App\Domains\Library\Actions\SaveLibraryItemAction::class)->execute([
            'title' => 'SMOKE-Primer',
            'slug' => 'smoke-primer',
            'content_type' => 'book',
            'access_type' => 'free_login',
            'description' => 'Planted by SmokeMarkerSeeder.',
            'body' => '<p>SMOKE-Primer-Page-One</p><!-- pagebreak --><p>SMOKE-Primer-Page-Two</p><!-- pagebreak --><p>SMOKE-Primer-Page-Three</p>',
        ]);
        app(\App\Domains\Library\Actions\PublishLibraryItemAction::class)->execute($item->id, (int) $approverId);

        // `SMOKE-Primer-PDF`: the same reader, fed from a PDF original and no
        // body — three pages printed by a browser, with English, Arabic and
        // Dhivehi. `reader.mjs` reads it page by page.
        $pdfPath = __DIR__.'/fixtures/smoke-primer.pdf';
        if (is_file($pdfPath)) {
            $pdf = new \Illuminate\Http\UploadedFile($pdfPath, 'smoke-primer.pdf', 'application/pdf', null, true);
            $pdfItem = app(\App\Domains\Library\Actions\SaveLibraryItemAction::class)->execute([
                'title' => 'SMOKE-Primer-PDF',
                'slug' => 'smoke-primer-pdf',
                'content_type' => 'book',
                'access_type' => 'free_login',
                'description' => 'Planted by SmokeMarkerSeeder from a PDF, no body.',
                'created_by' => (int) $approverId,
            ], null, $pdf);
            app(\App\Domains\Library\Actions\PublishLibraryItemAction::class)->execute($pdfItem->id, (int) $approverId);
        }
    }

    /**
     * `family.mjs` has the teacher write `SMOKE-Homework` into today's
     * register, the office post `SMOKE-Notice`, the family and the teacher
     * message each other (`SMOKE-Message`, `SMOKE-Reply`) and the teacher
     * poll the class (`SMOKE-Poll`). This plants nothing — the register is
     * the day's own and the people are seeded — and clears what a run left,
     * in foreign-key order: the poll answers, polls, participants and
     * messages of the smoke threads, the notifications they raised, the
     * notice, and the homework ticks; the homework itself is blanked on the
     * register rather than the register deleted, which is the day's record.
     */
    private function familyCycle(): void
    {
        // Only this walk's threads. `SMOKE-Thread` and `SMOKE-Thread-Not-Mine`
        // are planted above for `own-data.mjs`, and a `like 'SMOKE-%'` here
        // swept them away a moment after they were made — the family's own
        // thread then answered 403 and the own-data pair went inconclusive.
        $threadIds = DB::table('message_threads')->whereIn('subject', ['SMOKE-Message', 'SMOKE-Poll'])->pluck('id');
        $pollIds = DB::table('message_polls')->whereIn('message_thread_id', $threadIds)->pluck('id');
        DB::table('message_poll_responses')->whereIn('message_poll_id', $pollIds)->delete();
        DB::table('message_polls')->whereIn('id', $pollIds)->delete();
        DB::table('messages')->whereIn('thread_id', $threadIds)->delete();
        DB::table('message_participants')->whereIn('message_thread_id', $threadIds)->delete();
        DB::table('message_threads')->whereIn('id', $threadIds)->delete();
        DB::table('user_notifications')->where(fn ($q) => $q->where('title', 'like', '%SMOKE-%')->orWhere('message', 'like', '%SMOKE-%'))->delete();

        DB::table('announcements')->where('title', 'SMOKE-Notice')->delete();

        $logIds = DB::table('lesson_logs')->where('homework', 'like', 'SMOKE-Homework%')->pluck('id');
        DB::table('homework_ticks')->whereIn('lesson_log_id', $logIds)->delete();
        DB::table('lesson_logs')->whereIn('id', $logIds)->update(['homework' => null, 'homework_due_date' => null]);
    }

    /**
     * `signup.mjs` has the office build `SMOKE-Trip` — a sign-up with a fee
     * that a parent must confirm — the pupil answer it, the parent confirm
     * it, and the office close it. This plants nothing and clears what a run
     * left, in foreign-key order: the invoice the fee raised (with its lines;
     * nothing is paid against it), the answers, the sheet.
     */
    private function signupCycle(): void
    {
        $formIds = DB::table('forms')->where('title', 'SMOKE-Trip')->pluck('id');
        $invoiceIds = DB::table('form_responses')->whereIn('form_id', $formIds)->whereNotNull('invoice_id')->pluck('invoice_id');
        DB::table('form_responses')->whereIn('form_id', $formIds)->delete();
        DB::table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->delete();
        DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
        DB::table('forms')->whereIn('id', $formIds)->delete();
    }

    /**
     * `school-day.mjs` has the office publish one calendar day and keep
     * another internal, and the teacher mark the smoke pupil late on
     * today's register. This plants nothing and clears the two days and the
     * pupil's marks for today — the register itself is the day's record and
     * stays; `absence.mjs` re-marks it in its own run.
     */
    /**
     * `timetable.mjs` places one teacher in the same slot of two classes and
     * expects the second placement refused, then allowed with a reason. The
     * two classes are this seeder's own — `SMOKE-Class` A and B in the
     * active year, empty rosters, no timetable — so the walk never touches
     * a real class's week. Their entries are the walk's residue and go;
     * the classes stay, keyed by name and section.
     */
    /**
     * `scripts/smoke/requests.mjs` has the parent file two requests about
     * the pupil and the office decide them, which tells the class teacher
     * and the office and then the parent. This clears the requests and the
     * notices, and makes teacher@ the class teacher of the pupil's class
     * (`classes.class_teacher_id` is a users.id) so "the class teacher is
     * told" has someone to be.
     */
    private function requestsCycle(?ClassRoom $class): void
    {
        DB::table('requests')->where('reason', 'like', 'SMOKE-Family-Request%')->delete();
        DB::table('user_notifications')
            ->where(fn ($query) => $query->where('message', 'like', '%SMOKE-Family-Request%')->orWhere('message', 'like', '%SMOKE-Rejected%'))
            ->delete();

        $teacherUserId = (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');
        if ($class && $teacherUserId > 0) {
            DB::table('classes')->where('id', $class->id)->update(['class_teacher_id' => $teacherUserId]);
        }
    }

    private function timetableCycle(AcademicYear $year): void
    {
        $schoolId = DB::table('schools')->orderBy('id')->value('id');
        if ($schoolId === null) {
            return;
        }

        foreach (['A', 'B'] as $section) {
            DB::table('classes')->updateOrInsert(
                ['name' => 'SMOKE-Class', 'section' => $section, 'academic_year_id' => $year->id],
                ['school_id' => $schoolId, 'level' => 'Primary', 'capacity' => 20, 'is_active' => 1, 'description' => 'Planted by SmokeMarkerSeeder.', 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $classIds = DB::table('classes')->where('name', 'SMOKE-Class')->pluck('id');
        DB::table('timetables')->whereIn('class_id', $classIds)->delete();
    }

    private function schoolDayCycle(): void
    {
        DB::table('calendar_days')->whereIn('title', ['SMOKE-Sports-Day', 'SMOKE-Staff-Meeting'])->delete();
        // `create-sweep.mjs` adds a calendar day named `MADE<run>S25` on the
        // day after the last entry, every run. Left alone, those rows march
        // forward into the dates this walk adds a week out (unique on date and
        // year), and the fourth staging run's sports day was refused (§5fz).
        DB::table('calendar_days')->where('title', 'like', 'MADE%S25')->delete();

        $studentId = DB::table('students')->where('user_id', DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))->value('id');
        if ($studentId !== null) {
            // Every mark, not only today's: the lateness panel the walk reads
            // aggregates the year, and a host walked on two different days
            // showed the pupil late twice (fourth staging run, §5fz). Nothing
            // but the walks writes this pupil's attendance.
            DB::table('class_attendance')->where('student_id', $studentId)->delete();
        }
    }

    private function hr(AcademicYear $year, StaffProfile $staff, ?object $admin): void
    {
        // One active contract per profile: `$staff` is teacher@'s profile now,
        // and `hrCycle()` has already made sure it holds one. The marker's
        // distinctive salary goes on that contract rather than beside it.
        DB::table('staff_contracts')->where('basic_salary', 12345)->where('staff_profile_id', '!=', $staff->id)->delete();
        $activeContractId = DB::table('staff_contracts')->where('staff_profile_id', $staff->id)->where('status', 'active')->value('id');
        if ($activeContractId) {
            DB::table('staff_contracts')->where('id', $activeContractId)->update(['basic_salary' => 12345, 'updated_at' => now()]);
        } else {
            DB::table('staff_contracts')->insert([
                'staff_profile_id' => $staff->id, 'contract_type' => 'permanent',
                'start_date' => '2026-01-01', 'basic_salary' => 12345, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

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
