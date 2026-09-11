<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\EnsureTeacherRowAction;
use App\Domains\People\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `EnsureTeacherRowAction` backfills the `teachers` row for a user who has the
 * teacher role but no profile — the mitigation for the hazard that a `teachers`
 * row is not the same thing as the Spatie role `teacher`.
 *
 * It had no test at all until now, and it had a hole worth the name: `users`
 * makes `phone`, `address` and `email` nullable while `teachers` makes all three
 * NOT NULL, and the action copied them straight across. A user missing any of
 * them made it throw.
 *
 * That the author saw the mismatch is not in doubt — `date_of_birth` and
 * `gender` are nullable on `users` and NOT NULL on `teachers` too, and both were
 * already given fallbacks on the lines above. Three columns were simply missed.
 *
 * The fallback is an empty string rather than an invented value. A blank phone
 * says "we do not know"; a fabricated one is a number somebody might dial.
 */
it('creates a teacher row for a user missing phone, address and email', function () {
    $school = makeSchool();

    // A user with none of the three nullable columns set. This is the case that
    // threw: `SQLSTATE[23000] ... Column 'phone' cannot be null`.
    $user = User::factory()->create([
        'name' => 'Aishath Shifa',
        'phone' => null,
        'address' => null,
        'email' => null,
    ]);

    $teacher = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    expect($teacher->exists)->toBeTrue()
        ->and($teacher->user_id)->toBe((int) $user->id)
        ->and($teacher->first_name)->toBe('Aishath')
        ->and($teacher->last_name)->toBe('Shifa')
        ->and($teacher->phone)->toBe('')
        ->and($teacher->address)->toBe('')
        ->and($teacher->email)->toBe('');
});

it('copies the user contact details across when they are present', function () {
    $school = makeSchool();

    $user = User::factory()->create([
        'name' => 'Mariyam Ali',
        'phone' => '7770123',
        'address' => 'Malé',
        'email' => 'mariyam@example.test',
    ]);

    $teacher = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    expect($teacher->phone)->toBe('7770123')
        ->and($teacher->address)->toBe('Malé')
        ->and($teacher->email)->toBe('mariyam@example.test');
});

it('returns the existing row instead of creating a second one', function () {
    $school = makeSchool();
    $user = User::factory()->create(['name' => 'Hassan Adam', 'phone' => '7770124', 'address' => 'Hulhumalé']);

    $first = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);
    $second = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    // `teachers.user_id` carries a foreign key but no unique index, so nothing
    // in the database stops a second row. Idempotence is the action's job, and
    // this pins it.
    expect($second->id)->toBe($first->id)
        ->and(Teacher::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('gives a one-word name a usable last name', function () {
    $school = makeSchool();
    $user = User::factory()->create(['name' => 'Ibrahim', 'phone' => '7770125', 'address' => 'Malé']);

    $teacher = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    expect($teacher->first_name)->toBe('Ibrahim')
        ->and($teacher->last_name)->toBe('Ibrahim');
});
