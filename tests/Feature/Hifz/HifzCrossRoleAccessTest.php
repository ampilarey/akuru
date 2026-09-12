<?php

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\EnsureTeacherRowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\Permission\Models\Role;

/**
 * `docs/KNOWN_ISSUES.md` owner-decision 9 says the Hifz module "has no role
 * guard at all", listing every screen as exposed. That was written against the
 * route file, where it is literally true — `app/Domains/Hifz/routes.php` is
 * declared under `['auth', 'trackActivity']` and nothing else.
 *
 * It is not what the module *does*, and the difference decides how urgent the
 * role matrix is. Every surviving controller either authorizes explicitly or
 * scopes its query through `HifzScopeService`, which resolves the caller's role
 * before it reads a row. This test pins that, so the decision is taken against
 * measured behaviour rather than against a sentence.
 *
 * The fixture is deliberately **two families in one halaqa**: one child each,
 * same programme, same teacher. A scoping mistake is invisible when the fixture
 * has a single family — everything the parent can see is theirs by
 * construction. Here, family B's child is the canary.
 */
uses(RefreshDatabase::class);

function hifzTwoFamilyFixture(): array
{
    foreach (['parent', 'student', 'teacher', 'supervisor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $year = makeYear(['name' => 'Hifz scope year', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);

    $teacherUser = User::factory()->create(['name' => 'Halaqa Teacher', 'phone' => '7770201', 'address' => 'Malé']);
    $teacherUser->assignRole('teacher');
    app(EnsureTeacherRowAction::class)->execute((int) $teacherUser->id, (int) $class->school_id);
    $teacherId = (int) DB::table('teachers')->where('user_id', $teacherUser->id)->value('id');

    $program = HifzProgram::create([
        'name' => 'Halaqa One',
        'status' => 'active',
        'default_teacher_id' => $teacherId,
    ]);

    $family = function (string $label, string $phone) use ($class, $program, $teacherId): array {
        $studentUser = User::factory()->create(['name' => $label.' Pupil']);
        $studentUser->assignRole('student');

        $child = makeStudent(['first_name' => $label, 'last_name' => 'Pupil']);
        DB::table('students')->where('id', $child->id)->update([
            'user_id' => $studentUser->id,
            'class_id' => $class->id,
        ]);

        $parentUser = User::factory()->create(['name' => $label.' Parent']);
        $parentUser->assignRole('parent');
        $guardianId = DB::table('parent_guardians')->insertGetId([
            'user_id' => $parentUser->id, 'first_name' => $label, 'last_name' => 'Parent',
            'phone' => $phone, 'email' => strtolower($label).'.parent@example.test',
            'address' => 'Malé', 'relationship' => 'mother',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('guardian_student')->insert([
            'guardian_id' => $guardianId, 'student_id' => $child->id,
            'relationship' => 'mother', 'is_primary' => true, 'can_pickup' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        HifzEnrollment::create([
            'hifz_program_id' => $program->id,
            'student_id' => $child->id,
            'teacher_id' => $teacherId,
            'start_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        // An approved milestone gives the dashboards and the milestone report
        // something of this family's to render — otherwise every screen is
        // empty and "no leak" proves nothing.
        // `type` is an enum in the schema and `completed_at` is NOT NULL —
        // both read off the migration rather than guessed, for the reason the
        // activity fixture learned the hard way: an invented enum value inserts
        // cleanly and throws only when the model casts it back.
        HifzMilestone::create([
            'hifz_program_id' => $program->id,
            'student_id' => $child->id,
            'type' => 'juz_completed',
            'juz_number' => 1,
            'title' => $label.' finished juz 1',
            'completed_at' => now()->subWeek(),
            'status' => 'approved',
        ]);

        return [
            'parent' => $parentUser->fresh(),
            'student' => $studentUser->fresh(),
            'child_id' => (int) $child->id,
            'name' => $label.' Pupil',
        ];
    };

    return [
        'program' => $program,
        'teacher' => $teacherUser->fresh(),
        'a' => $family('Aishath', '7770202'),
        'b' => $family('Bassam', '7770203'),
    ];
}

/** @return list<string> every GET path under /hifz that needs no route parameter */
function hifzGetPaths(): array
{
    $paths = [];
    foreach (RouteFacade::getRoutes() as $route) {
        $uri = $route->uri();
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        if ($uri !== 'hifz' && ! str_starts_with($uri, 'hifz/')) {
            continue;
        }
        if (str_contains($uri, '{')) {
            continue;
        }
        $paths[] = $uri;
    }

    sort($paths);

    return array_values(array_unique($paths));
}

it('never shows one family another family on any Hifz screen', function () {
    $cast = hifzTwoFamilyFixture();
    $paths = hifzGetPaths();

    // If the sweep ever silently covers nothing, this is the line that fails.
    expect($paths)->not->toBeEmpty()
        ->and($paths)->toContain('hifz/parent', 'hifz/reports/weak-students');

    $leaks = [];
    $crashes = [];
    // A "no leak" result is worthless unless the detector can see a name at
    // all. `$sawOwn` records where each caller's *own* child did appear, and is
    // asserted below — if the fixture ever stops rendering names, this test
    // fails loudly instead of passing vacuously.
    $sawOwn = [];

    foreach ([['a', 'b'], ['b', 'a']] as [$mine, $theirs]) {
        foreach (['parent', 'student'] as $who) {
            $actor = $cast[$mine][$who];
            $otherChild = $cast[$theirs]['name'];
            $ownChild = $cast[$mine]['name'];

            foreach ($paths as $path) {
                $response = $this->withoutLocalizationMiddleware()
                    ->actingAs($actor)
                    ->followingRedirects()
                    ->get('/'.$path);

                if ($response->getStatusCode() >= 500) {
                    $crashes[] = sprintf('%s as %s %s → %d', $path, $mine, $who, $response->getStatusCode());

                    continue;
                }

                // A refusal is a perfectly good answer here; only a rendered
                // page carrying the other family's child is a finding.
                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                if (str_contains($response->getContent(), $otherChild)) {
                    $leaks[] = sprintf('%s as %s %s leaks "%s"', $path, $mine, $who, $otherChild);
                }

                if (str_contains($response->getContent(), $ownChild)) {
                    $sawOwn[$mine.' '.$who][] = $path;
                }
            }
        }
    }

    expect($crashes)->toBeEmpty("Hifz screens crashed:\n".implode("\n", $crashes));

    expect(array_keys($sawOwn))->toHaveCount(
        4,
        'Every caller must have seen their own child somewhere, or the leak check above proved nothing. Saw: '
        .json_encode($sawOwn)
    );
    expect($leaks)->toBeEmpty(
        "A Hifz screen showed one family another family's child:\n".implode("\n", $leaks)
    );
});

it('refuses the reports to anyone without view_hifz_reports', function () {
    $cast = hifzTwoFamilyFixture();

    // The reports are the one Hifz surface that reads across families, and they
    // are gated on a permission rather than on scoping — worth asserting apart
    // from the sweep, because "empty because scoped" and "refused" are
    // different guarantees and only one of them survives a scoping bug.
    foreach (hifzGetPaths() as $path) {
        if (! str_starts_with($path, 'hifz/reports')) {
            continue;
        }

        $this->withoutLocalizationMiddleware()
            ->actingAs($cast['a']['parent'])
            ->get('/'.$path)
            ->assertForbidden();
    }
});

it('sends each role to its own dashboard from the hub, and refuses anyone else', function () {
    $cast = hifzTwoFamilyFixture();

    $this->withoutLocalizationMiddleware()->actingAs($cast['a']['parent'])
        ->get('/hifz')->assertRedirect(route('hifz.parent.dashboard'));

    $this->withoutLocalizationMiddleware()->actingAs($cast['a']['student'])
        ->get('/hifz')->assertRedirect(route('hifz.student.dashboard'));

    $this->withoutLocalizationMiddleware()->actingAs($cast['teacher'])
        ->get('/hifz')->assertRedirect(route('hifz.teacher.dashboard'));

    // `HifzHubController` is the one surviving controller with neither an
    // `authorize` call nor a scoped query. It ends in `abort(403)`, and this is
    // the assertion that says so.
    $nobody = User::factory()->create(['name' => 'Unroled Person']);
    $this->withoutLocalizationMiddleware()->actingAs($nobody)
        ->get('/hifz')->assertForbidden();
});
