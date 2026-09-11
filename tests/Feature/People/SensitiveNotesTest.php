<?php

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ArchiveSensitiveNoteAction;
use App\Domains\People\Actions\ListSensitiveNotesAction;
use App\Domains\People\Actions\ListSensitiveNoteViewsAction;
use App\Domains\People\Actions\SaveSensitiveNoteAction;
use App\Domains\People\Models\SensitiveNoteView;
use App\Domains\People\Models\StudentSensitiveNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E19 — student sensitive information.
 *
 * The plan calls this *"the one module where building first and deciding later
 * is actively wrong"*, and lists the three undecided questions: who may read,
 * retention, and whether it is exportable.
 *
 * These tests assert that each of those **fails closed and stays visible**
 * rather than being answered quietly — especially the first, because
 * `RoleSeeder` grants `admin` `Permission::all()` and doing nothing would have
 * handed every admin account every child's health note by accident.
 */
function sensitiveSetup(): array
{
    makeYear(['name' => 'Welfare year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);

    foreach (['sensitive.read', 'sensitive.write'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $head = Role::findOrCreate('headmaster', 'web');
    $head->givePermissionTo(['sensitive.read', 'sensitive.write']);

    // An ordinary admin, granted everything the seeder grants — which is
    // everything that exists. The point of the test below is that this is
    // still not enough.
    $adminRole = Role::findOrCreate('admin', 'web');
    $adminRole->givePermissionTo(Permission::all());

    $headmaster = User::factory()->create(['name' => 'Headmaster']);
    $headmaster->assignRole('headmaster');

    $admin = User::factory()->create(['name' => 'Office Admin']);
    $admin->assignRole('admin');

    return [
        'student' => makeStudent(['first_name' => 'Yoosuf', 'last_name' => 'Adam']),
        'headmaster' => $headmaster->fresh(),
        'admin' => $admin->fresh(),
    ];
}

it('keeps the screen away from an admin who holds every permission', function () {
    // RoleSeeder gives admin Permission::all(), so the permission alone cannot
    // be the gate — the role list has to exclude admin as well, and it does.
    ['headmaster' => $headmaster, 'admin' => $admin] = sensitiveSetup();

    expect($admin->can('sensitive.read'))->toBeTrue();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('people.sensitive.index'))->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)
        ->get(route('people.sensitive.index'))->assertOk();
});

it('refuses an ordinary member of staff outright', function () {
    ['student' => $student] = sensitiveSetup();

    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $this->withoutLocalizationMiddleware()->actingAs($teacher->fresh())
        ->get(route('people.sensitive.index'))->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($teacher->fresh())
        ->post(route('people.sensitive.store'), [
            'student_id' => $student->id, 'category' => 'medical', 'summary' => 'Peanut allergy',
        ])->assertForbidden();
});

it('logs every read, including the one that finds nothing', function () {
    // "Somebody went looking" is itself worth knowing, and the question
    // cannot be answered retrospectively if it was never recorded.
    ['student' => $student, 'headmaster' => $headmaster] = sensitiveSetup();

    expect(app(ListSensitiveNotesAction::class)->execute($student->id, $headmaster->id))->toHaveCount(0);
    expect(SensitiveNoteView::query()->count())->toBe(1);

    app(SaveSensitiveNoteAction::class)->execute([
        'student_id' => $student->id, 'category' => 'medical', 'summary' => 'Peanut allergy — epipen in the office',
    ], $headmaster->id);

    app(ListSensitiveNotesAction::class)->execute($student->id, $headmaster->id);

    $views = app(ListSensitiveNoteViewsAction::class)->execute($student->id);
    expect($views)->toHaveCount(2)->and($views->first()['who'])->toBe('Headmaster');
});

it('shows the access log on the same screen as the notes', function () {
    // Somebody about to read a child's welfare record should be able to see
    // that their own name will appear on this list — not have it buried in an
    // audit page nobody opens.
    ['student' => $student, 'headmaster' => $headmaster] = sensitiveSetup();

    app(SaveSensitiveNoteAction::class)->execute([
        'student_id' => $student->id, 'category' => 'welfare', 'summary' => 'Living with grandmother this term',
    ], $headmaster->id);

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)
        ->get(route('people.sensitive.index', ['student_id' => $student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('People/Sensitive/Index')
            ->has('notes', 1)
            ->has('views', 1)
            ->etc());
});

it('does not log a read when nobody has been chosen', function () {
    // Opening the search box is not reading a child's record.
    ['headmaster' => $headmaster] = sensitiveSetup();

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)
        ->get(route('people.sensitive.index'))->assertOk();

    expect(SensitiveNoteView::query()->count())->toBe(0);
});

it('lets only the author change their own note', function () {
    // A welfare note is somebody's professional observation with their name on
    // it. A second reader who disagrees adds their own, so the disagreement is
    // part of the record rather than replacing it.
    ['student' => $student, 'headmaster' => $headmaster] = sensitiveSetup();

    $other = User::factory()->create(['name' => 'Deputy']);
    $other->assignRole('headmaster');

    $note = app(SaveSensitiveNoteAction::class)->execute([
        'student_id' => $student->id, 'category' => 'welfare', 'summary' => 'Original observation',
    ], $headmaster->id);

    expect(fn () => app(SaveSensitiveNoteAction::class)
        ->execute(['category' => 'welfare', 'summary' => 'Rewritten'], $other->id, $note))
        ->toThrow(ValidationException::class);

    $updated = app(SaveSensitiveNoteAction::class)
        ->execute(['category' => 'welfare', 'summary' => 'Corrected by its author'], $headmaster->id, $note);

    expect($updated->summary)->toBe('Corrected by its author');
});

it('archives rather than deletes, because retention is undecided', function () {
    // Deleting a child's allergy history because a retention rule was assumed
    // is the failure that cannot be undone.
    ['student' => $student, 'headmaster' => $headmaster] = sensitiveSetup();

    $note = app(SaveSensitiveNoteAction::class)->execute([
        'student_id' => $student->id, 'category' => 'dietary', 'summary' => 'No pork',
    ], $headmaster->id);

    app(ArchiveSensitiveNoteAction::class)->execute($note->id, $headmaster->id);

    expect(StudentSensitiveNote::query()->count())->toBe(1)
        ->and(StudentSensitiveNote::query()->inUse()->count())->toBe(0)
        ->and(app(ListSensitiveNotesAction::class)->execute($student->id, $headmaster->id))->toHaveCount(0)
        ->and(app(ListSensitiveNotesAction::class)->execute($student->id, $headmaster->id, includeArchived: true))
        ->toHaveCount(1);

    expect(fn () => app(ArchiveSensitiveNoteAction::class)->execute($note->id, $headmaster->id))
        ->toThrow(ValidationException::class);

    // And an archived note cannot be quietly edited back into use.
    expect(fn () => app(SaveSensitiveNoteAction::class)
        ->execute(['category' => 'dietary', 'summary' => 'Reopened'], $headmaster->id, $note->refresh()))
        ->toThrow(ValidationException::class);
});

it('requires a one-line summary somebody can act on', function () {
    ['student' => $student, 'headmaster' => $headmaster] = sensitiveSetup();

    expect(fn () => app(SaveSensitiveNoteAction::class)
        ->execute(['student_id' => $student->id, 'category' => 'medical', 'body' => 'lots of detail'], $headmaster->id))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SaveSensitiveNoteAction::class)
        ->execute(['category' => 'medical', 'summary' => 'No pupil chosen'], $headmaster->id))
        ->toThrow(ValidationException::class);
});

it('exposes no export route and no family-facing route', function () {
    // The repo convention is that every listing gets a CSV. This is the one
    // table where "somebody exported it" is the incident, so the convention is
    // deliberately not followed — and that has to be asserted, or a later
    // slice adds one for consistency.
    $names = collect(app('router')->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->filter(fn (string $name) => str_contains($name, 'sensitive'))
        ->values();

    expect($names->all())->toBe([
        'people.sensitive.index',
        'people.sensitive.store',
        'people.sensitive.update',
        'people.sensitive.archive',
    ]);

    expect($names->filter(fn ($n) => str_contains($n, 'export')))->toHaveCount(0)
        ->and($names->filter(fn ($n) => str_starts_with($n, 'portal.')))->toHaveCount(0);
});

it('walks the screen over http', function () {
    ['student' => $student, 'headmaster' => $headmaster] = sensitiveSetup();

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)
        ->post(route('people.sensitive.store'), [
            'student_id' => $student->id,
            'category' => 'medical',
            'summary' => 'Peanut allergy — epipen in the office',
            'body' => 'Mother called on 3 September.',
            'review_on' => now()->addMonths(6)->toDateString(),
        ])->assertSessionHasNoErrors();

    $note = StudentSensitiveNote::query()->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)
        ->put(route('people.sensitive.update', $note->id), [
            'category' => 'medical', 'summary' => 'Peanut allergy — epipen in the school bag',
        ])->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)
        ->post(route('people.sensitive.archive', $note->id))->assertSessionHasNoErrors();

    expect($note->refresh()->archived_at)->not->toBeNull()
        ->and($note->summary)->toBe('Peanut allergy — epipen in the school bag');
});
