<?php

use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Detail screens — the pages that show one record — load without crashing.
 *
 * `StaffScreensDoNotCrashTest` and `PortalScreensDoNotCrashTest` between them
 * sweep every screen with no `{parameter}`. That leaves the pages that actually
 * display data, which are the ones most likely to fall over on a null relation,
 * and until this test nothing loaded a single one of them.
 *
 * **How a route gets swept — two ways, neither of them guessing.**
 *
 * 1. **Reflection.** The controller's signature says which model a parameter
 *    is: `ClassRoom $classRoom` names the table exactly.
 * 2. **A declared value** in `detailDeclaredParams()`, keyed by route URI, for
 *    controllers that take `int $course` and resolve through an Action so there
 *    is nothing to reflect.
 *
 * What is never done is inferring from the parameter's *name*. `{session}` is a
 * Hifz session on one route and an offering session on another; `{course}` is an
 * ordinary course on `catalog/courses/{course}/outline` and a **club** on
 * `academics/clubs/{club}`. A guard that guesses between those by name is a
 * guard that asserts nothing.
 *
 * **The rows come from the app's own seeders** (`DatabaseSeeder`, which
 * includes `PilotRehearsalSeeder`) wherever it provides them, so the sweep runs
 * against representative data rather than against rows invented to make it
 * pass. Where it provides none, a small builder below creates one.
 *
 * The assertion is the same narrow one as its siblings: **no 5xx**. 403, 404
 * and redirects are all allowed — a detail screen may legitimately refuse, and
 * one pointed at a row it cannot show should 404 rather than throw.
 *
 * **What that costs, stated plainly.** A screen whose row does not suit it
 * lands on its refusal branch, and then the sweep proves only that the route
 * does not throw — not that the page renders. `admin/public-site/research/{post}/edit`
 * is the live example: `PresentResearchPostAction` returns null for a post that
 * is not a research post, and the controller 404s on purpose, so the fixture
 * row exercises the guard clause rather than the form. Rendering is what the
 * browser walk checks; this test is the floor beneath it, not a substitute.
 */

/**
 * Routes still out of reach, and why. Pinned rather than counted: if a new
 * detail route lands that cannot be resolved, this list stops matching and the
 * test fails, forcing the decision instead of silently skipping the screen.
 *
 * Every entry here is a screen with **no crash coverage**. The list is meant to
 * shrink, and it already has: `admin/public-site/courses/{course}` and
 * `announcements/{announcement}/edit` were on it, described as taking an int
 * parameter. That reason was wrong. Reflection found no binding because those
 * controllers had **no such method at all**, and both routes answered 500 to
 * anyone who reached them. They are gone now, along with three sibling write
 * routes, and `RoutesHaveControllerMethodsTest` keeps the whole class out.
 *
 * The lesson is worth keeping next to the list: an entry here says "not
 * covered", never "not broken". A plausible-sounding reason is the easiest
 * place for a live fault to hide.
 *
 * @return array<string, string> uri => reason
 */
function unresolvedDetailScreens(): array
{
    return [
        // Scalars with no row behind them, and one route needing a second row
        // (an offering *session*) that nothing seeds yet.
        'catalog/offerings/{offering}/sessions/{session}/attendance' => 'needs an offering session row as well as the offering',
        'hifz/quran/mushafs/{mushaf}/pages/{pageNumber}' => 'page number is a scalar, not a row',
        'payments/ref/{merchant_reference}/status' => 'string reference, not a row',

        // Family screens. These resolve as `int` too, but sweeping them as a
        // super_admin would prove the wrong thing: they are scoped to the
        // parent, student or teacher who owns the record, so they need the
        // portal cast from `PortalScreensDoNotCrashTest`. That is a slice of
        // its own, not a fixture tweak.
        'learn/activities/{activity}' => 'int param, and a family screen: needs the portal cast',
        'learn/assessments/{assessment}' => 'int param, family screen',
        'learn/courses/{course}' => 'int param, family screen',
        'learn/lessons/{lesson}' => 'int param, family screen',
        'learn/media/{media}' => 'int param, family screen',
        'portal/messages/{thread}' => 'int param, family screen',
        'teach/quran-sessions/{session}' => 'int param, family screen',

        // Bound to a model, but no row: building one means fabricating Hifz
        // programme structure or a payment. Payments especially are left alone
        // — the ledger is append-only (rule 12) and a sweep has no business
        // inventing rows in it.
        'hifz/programs/{program}' => 'no HifzProgram row; Hifz is frozen (rule 7)',
        'hifz/programs/{program}/edit' => 'no HifzProgram row',
        'hifz/programs/{program}/enrollments' => 'no HifzProgram row',
        'hifz/programs/{program}/enrollments/create' => 'no HifzProgram row',
        'hifz/quran/mushafs/{mushaf}' => 'no QuranMushaf row',
        'hifz/quran/mushafs/{mushaf}/words' => 'no QuranMushaf row',
        'hifz/session-records/{record}/quran-page' => 'no HifzSessionRecord row',
        'hifz/sessions/{session}/edit' => 'no HifzSession row',
        'quran-progress/{quran_progress}' => 'no QuranProgress row',
        'quran-progress/{quran_progress}/edit' => 'no QuranProgress row',
        'payments/return/{payment}' => 'money path; a sweep does not invent ledger rows',
        'payments/status/{payment}' => 'money path',
        'payments/{payment}/receipt' => 'money path',
        'admin/enrollments/{enrollment}' => 'no CourseEnrollment row',
        'exams/{exam}/marks' => 'an Exam needs year, term, class, subject and exam type',
        'substitutions/absences/{absence}/edit' => 'no TeacherAbsence row',
        'substitutions/requests/{request}' => 'no SubstitutionRequest row',
        'substitutions/requests/{request}/edit' => 'no SubstitutionRequest row',
        'admin/prayer-times/groups/{group}/edit' => 'no PrayerRecipientGroup row',
        'admin/prayer-times/broadcasts/{broadcast}/edit' => 'no PrayerBroadcast row',
    ];
}

/**
 * The second way a route gets swept: a **declared** parameter value, for
 * controllers that take `int $course` and resolve through an Action rather than
 * type-hinting a model.
 *
 * Keyed by route URI, never by parameter name. `{session}` is a Hifz session on
 * one route and an offering session on another, and `{course}` is an ordinary
 * course on `catalog/courses/{course}/outline` but a **club** on
 * `academics/clubs/{club}` — clubs have no table of their own, they are
 * `courses` rows carrying `course_type = 'club'` (E17, rule 11). Declaring by
 * URI keeps each of those explicit instead of collapsing them into one guess.
 *
 * @return array<string, callable(array<string, int|string>): (array<string, int|string>|null)>
 */
function detailDeclaredParams(): array
{
    $course = fn (array $seeded): array => ['course' => $seeded['course']];

    return [
        'academics/clubs/{club}' => fn (array $s): array => ['club' => $s['club']],
        'academics/clubs/{club}/attendance-sheet' => fn (array $s): array => ['club' => $s['club']],
        'catalog/courses/{course}/outline' => $course,
        'catalog/courses/{course}/activities' => $course,
        'catalog/courses/{course}/assessments' => $course,
        'catalog/offerings/{offering}/sessions' => fn (array $s): array => ['offering' => $s['offering']],
        'catalog/player/{lesson}' => fn (array $s): array => ['lesson' => $s['lesson']],
    ];
}

/**
 * The rows those declared routes point at. Built once per test run.
 *
 * `DatabaseSeeder` provides ten courses but **no** offerings, modules, lessons
 * or clubs, and every seeded course is `course_type = 'general'` — checked
 * rather than assumed, after an earlier count of mine read the walk database by
 * mistake and made them look present.
 *
 * @return array<string, int|string>
 */
function detailSeededIds(): array
{
    $courseClass = \App\Domains\Courses\Models\Course::class;
    $course = $courseClass::query()->firstOrFail();

    $club = $courseClass::query()->create([
        'course_category_id' => $course->course_category_id,
        'title' => 'Chess club',
        'slug' => 'chess-club-'.Str::random(6),
        'short_desc' => 'Thursdays after school.',
        'body' => 'Open to Grade 4 and up.',
        'cover_image' => '',
        'workflow_status' => 'published',
        'course_type' => 'club',
        'status' => 'open',
    ]);

    $offering = \Illuminate\Support\Facades\DB::table('course_offerings')->insertGetId([
        'course_id' => $course->id,
        'title' => 'Term 1 offering',
        'slug' => 'term-1-offering-'.Str::random(6),
        'delivery_mode' => 'self_learning',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moduleId = \Illuminate\Support\Facades\DB::table('course_modules')->insertGetId([
        'course_id' => $course->id,
        'title' => 'Module one',
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lessonId = \Illuminate\Support\Facades\DB::table('lessons')->insertGetId([
        'course_id' => $course->id,
        'course_module_id' => $moduleId,
        'title' => 'Lesson one',
        'slug' => 'lesson-one-'.Str::random(6),
        'position' => 1,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // A lesson without a published revision is a 404 on the player by design
    // (`ResolvePublishedLessonAction` returns null and the controller aborts).
    // The sweep would still "pass" on that 404 while proving only that the
    // route does not throw — so the revision is built, and the player renders.
    $revisionId = \Illuminate\Support\Facades\DB::table('lesson_revisions')->insertGetId([
        'lesson_id' => $lessonId,
        'revision_number' => 1,
        'snapshot_json' => json_encode([
            'title' => 'Lesson one',
            'blocks' => [],
        ]),
        'published_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \Illuminate\Support\Facades\DB::table('lessons')
        ->where('id', $lessonId)
        ->update(['current_revision_id' => $revisionId]);

    return [
        'course' => (int) $course->id,
        'club' => (int) $club->id,
        'offering' => (int) $offering,
        'lesson' => (int) $lessonId,
    ];
}

/**
 * One row per model the sweep needs, preferring what the seeders already
 * produced. Only the cheap ones are built here; the rest are named in
 * `unresolvedDetailScreens()` rather than half-built.
 */
function detailFixtureRow(string $class, User $actor): ?Model
{
    $existing = $class::query()->first();

    if ($existing !== null) {
        return $existing;
    }

    return match (class_basename($class)) {
        'Announcement' => makeNotice(),
        'LessonLog' => makeLessonLog(),
        'DailyContent' => w23Published(),
        'BookTitle' => $class::query()->create(['title' => 'A Borrowed Book']),
        'Instructor' => $class::query()->create(['name' => 'Walk Instructor', 'slug' => 'walk-instructor']),
        'Event' => $class::query()->create([
            'title' => 'Sports day',
            'slug' => 'sports-day-'.Str::random(6),
            'description' => 'On the field.',
            'location' => 'Main field',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(3),
        ]),
        'Form' => $class::query()->create([
            'created_by' => $actor->id,
            'title' => 'Trip consent',
            'fields' => [],
        ]),
        'Post' => $class::query()->create([
            'title' => 'A research note',
            'slug' => 'a-research-note-'.Str::random(6),
            'summary' => 'Short summary.',
            'body' => 'Body text.',
            'author_id' => $actor->id,
        ]),
        default => null,
    };
}

it('loads every resolvable detail screen without a server error', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    $role = Role::findOrCreate('super_admin', 'web');
    $role->givePermissionTo(Permission::all());

    $actor = User::factory()->create(['name' => 'Detail Sweeper']);
    $actor->assignRole('super_admin');

    $byUri = [];
    foreach (RouteFacade::getRoutes() as $route) {
        if (in_array('GET', $route->methods(), true)) {
            $byUri[$route->uri()][] = $route;
        }
    }

    $crashed = [];
    $swept = [];
    $unresolved = [];

    $seededIds = detailSeededIds();
    $declared = detailDeclaredParams();

    foreach (detailScreens() as [$name, $uri, $params]) {
        $route = null;
        foreach ($byUri[$uri] ?? [] as $candidate) {
            if (($candidate->getName() ?? '') === $name) {
                $route = $candidate;
            }
        }
        $route ??= ($byUri[$uri][0] ?? null);

        if ($route === null) {
            continue;
        }

        // A declared value wins over reflection: these routes have no model to
        // reflect, which is the whole reason they are declared.
        if (array_key_exists($uri, $declared)) {
            $values = $declared[$uri]($seededIds);
            $path = $uri;

            foreach ($values as $param => $value) {
                $path = str_replace('{'.$param.'}', (string) $value, $path);
            }

            $swept[] = $uri;

            try {
                $response = $this->withoutLocalizationMiddleware()
                    ->actingAs($actor->fresh())
                    ->get('/'.$path);

                if ($response->getStatusCode() >= 500) {
                    $crashed[] = sprintf('%s (%s) → %d', $path, $name, $response->getStatusCode());
                }
            } catch (Throwable $e) {
                $crashed[] = sprintf('%s (%s) threw %s: %s', $path, $name, $e::class, $e->getMessage());
            }

            continue;
        }

        $bindings = routeModelBindings($route);
        $path = $uri;
        $resolved = true;

        foreach ($params as $param) {
            $class = $bindings[$param] ?? null;
            $row = $class === null ? null : detailFixtureRow($class, $actor);

            if ($row === null) {
                $resolved = false;

                break;
            }

            $path = str_replace('{'.$param.'}', (string) $row->getRouteKey(), $path);
        }

        if (! $resolved) {
            $unresolved[$uri] = true;

            continue;
        }

        $swept[] = $uri;

        try {
            $response = $this->withoutLocalizationMiddleware()
                ->actingAs($actor->fresh())
                ->get('/'.$path);

            if ($response->getStatusCode() >= 500) {
                $crashed[] = sprintf('%s (%s) → %d', $path, $name, $response->getStatusCode());
            }
        } catch (Throwable $e) {
            $crashed[] = sprintf('%s (%s) threw %s: %s', $path, $name, $e::class, $e->getMessage());
        }
    }

    // The gap is pinned, not counted. A new unresolvable detail route fails
    // here rather than slipping through unswept.
    $stillUnresolved = array_keys($unresolved);
    sort($stillUnresolved);
    $declared = array_keys(unresolvedDetailScreens());
    sort($declared);

    expect($stillUnresolved)->toBe(
        $declared,
        "The set of unsweepable detail screens changed.\nAdd the new one to\n"
        ."unresolvedDetailScreens() with its reason, or — better — give it a row\n"
        ."in detailFixtureRow() so it is actually swept.\n"
    );

    // If this collapses, the resolution above silently stopped working and the
    // test is loading nothing.
    expect(count($swept))->toBeGreaterThan(15);

    expect($crashed)->toBeEmpty(
        count($crashed)." detail screen(s) return a server error:\n".implode("\n", $crashed)
    );
});
