<?php

use App\Domains\Academics\Actions\ApproveAbsenceNoteAction;
use App\Domains\Academics\Actions\ListAbsenceTypesAction;
use App\Domains\Academics\Actions\SaveAbsenceTypeAction;
use App\Domains\Academics\Actions\SubmitAbsenceNoteAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\AbsenceType;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E10c — absence reasons the school defines for itself.
 *
 * The five reasons were a MySQL enum, repeated in a controller list and again
 * in its validation rule. More importantly each reason now carries **what it
 * does**: whether approving it clears the register, and whether a document is
 * required.
 */
function absenceTypeSetup(): array
{
    makeYear(['name' => 'Absence year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);

    Role::findOrCreate('admin', 'web');
    $staff = User::factory()->create(['name' => 'Office']);
    $staff->assignRole('admin');

    return ['staff' => $staff->fresh(), 'student' => makeStudent(['first_name' => 'Sana', 'last_name' => 'Adam'])];
}

it('ships the five old reasons as rows, so nothing changes meaning', function () {
    // The migration seeds them. A school wakes up to the behaviour it had.
    $codes = AbsenceType::query()->orderBy('sort_order')->pluck('code')->all();

    expect($codes)->toBe(['illness', 'medical_appointment', 'family_emergency', 'religious', 'other']);

    // All excuse the register, which is exactly what the old per-note
    // `affects_attendance` default did.
    expect(AbsenceType::query()->where('excuses_absence', true)->count())->toBe(5);
});

it('lets a school add its own reason', function () {
    ['staff' => $staff] = absenceTypeSetup();

    $type = app(SaveAbsenceTypeAction::class)->execute([
        'name' => 'Sports fixture',
        'name_dhivehi' => 'ކުޅިވަރު',
        'excuses_absence' => true,
    ]);

    expect($type->code)->toBe('sports_fixture')
        ->and($type->name_dhivehi)->toBe('ކުޅިވަރު');

    // It reaches families without a deploy, and goes to the *end* of the list
    // rather than being spliced between the seeded reasons — which is what a
    // sort_order defaulting to 0 did, as the browser walk showed.
    $offered = app(ListAbsenceTypesAction::class)->execute()->pluck('code');
    expect($offered)->toContain('sports_fixture')
        ->and($offered->last())->toBe('sports_fixture');

    // Duplicate codes are refused.
    expect(fn () => app(SaveAbsenceTypeAction::class)->execute(['name' => 'Sports fixture']))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SaveAbsenceTypeAction::class)->execute(['name' => '   ']))
        ->toThrow(ValidationException::class);

    unset($staff);
});

it('decides from the type whether approval clears the register', function () {
    // This is the real point of the slice. It used to be a per-note boolean,
    // so the policy was chosen one note at a time by whoever filled the form.
    ['staff' => $staff, 'student' => $student] = absenceTypeSetup();

    $unexcused = app(SaveAbsenceTypeAction::class)->execute([
        'name' => 'Unauthorised holiday',
        'excuses_absence' => false,
    ]);

    $note = app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $student->id,
        'created_by' => $staff->id,
        'date' => now()->toDateString(),
        'reason' => 'Family trip in term time',
        'absence_type_id' => $unexcused->id,
    ]);

    // The note records the type, and keeps the old column in step (rule 9:
    // this is the deploy that stops reading it, not the one that drops it).
    expect((int) $note->absence_type_id)->toBe((int) $unexcused->id)
        ->and($note->type)->toBe('unauthorised_holiday')
        ->and((bool) $note->affects_attendance)->toBeFalse();

    app(ApproveAbsenceNoteAction::class)->execute($note, $staff->id);

    expect($note->refresh()->status)->toBe('approved');

    // An excusing type behaves the other way.
    $illness = AbsenceType::query()->where('code', 'illness')->firstOrFail();
    $second = app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $student->id,
        'created_by' => $staff->id,
        'date' => now()->toDateString(),
        'reason' => 'Fever',
        'absence_type_id' => $illness->id,
    ]);

    expect((bool) $second->affects_attendance)->toBeTrue();
});

it('refuses a note whose reason needs a document', function () {
    // The honest half of the plan's "can't they be falsified?": you cannot
    // stop a parent writing what they like, but you can require a document.
    ['staff' => $staff, 'student' => $student] = absenceTypeSetup();

    $needsDoc = app(SaveAbsenceTypeAction::class)->execute([
        'name' => 'Long-term illness',
        'requires_evidence' => true,
    ]);

    $submit = fn (?string $path) => app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $student->id,
        'created_by' => $staff->id,
        'date' => now()->toDateString(),
        'reason' => 'Signed off for a fortnight',
        'absence_type_id' => $needsDoc->id,
        'attachment_path' => $path,
    ]);

    expect(fn () => $submit(null))->toThrow(ValidationException::class);
    expect(AbsenceNote::query()->count())->toBe(0);

    $note = $submit('absence-notes/letter.pdf');
    expect($note->attachment_path)->toBe('absence-notes/letter.pdf');
});

it('retires a reason instead of deleting it', function () {
    // A retired reason is still the reason on last term's notes.
    ['staff' => $staff, 'student' => $student] = absenceTypeSetup();

    $type = app(SaveAbsenceTypeAction::class)->execute(['name' => 'Old reason']);

    app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $student->id,
        'created_by' => $staff->id,
        'date' => now()->toDateString(),
        'reason' => 'Whatever it was',
        'absence_type_id' => $type->id,
    ]);

    app(SaveAbsenceTypeAction::class)->execute(['name' => 'Old reason', 'is_active' => false], $type);

    // Gone from the family's list…
    expect(app(ListAbsenceTypesAction::class)->execute()->pluck('code'))->not->toContain('old_reason');
    // …still on the office's, and still attached to the note.
    expect(app(ListAbsenceTypesAction::class)->execute(selectableOnly: false)->pluck('code'))->toContain('old_reason');
    expect(AbsenceNote::query()->where('absence_type_id', $type->id)->count())->toBe(1);

    // And its code cannot be rewritten once notes point at it.
    expect(fn () => app(SaveAbsenceTypeAction::class)
        ->execute(['name' => 'Old reason', 'code' => 'something_else'], $type->refresh()))
        ->toThrow(ValidationException::class);
});

it('backfilled existing notes rather than stranding them', function () {
    // Rule 9: the migration added the column, seeded the types and pointed
    // every existing note at the right one.
    ['staff' => $staff, 'student' => $student] = absenceTypeSetup();

    // A note written the old way, with only the enum string.
    $id = DB::table('absence_notes')->insertGetId([
        'student_id' => $student->id,
        'created_by' => $staff->id,
        'date' => now()->toDateString(),
        'reason' => 'Legacy note',
        'type' => 'religious',
        'status' => 'submitted',
        'affects_attendance' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The reader still resolves it: approval falls back to the note's own
    // boolean when no type id is set.
    $note = AbsenceNote::query()->findOrFail($id);
    expect($note->absence_type_id)->toBeNull();

    app(ApproveAbsenceNoteAction::class)->execute($note, $staff->id);
    expect($note->refresh()->status)->toBe('approved');
});

it('still accepts the old `type` code from a client that has not been redeployed', function () {
    // The form sends an id now. Making that the only accepted field broke
    // `AbsenceNoteTest` — which is the suite doing its job: a hard break here
    // would silently stop any client still posting `type`, the mobile
    // scaffold included. Both are accepted for the transition.
    ['staff' => $staff, 'student' => $student] = absenceTypeSetup();

    Role::findOrCreate('parent', 'web');
    $parent = User::factory()->create();
    $parent->assignRole('parent');

    $guardianId = DB::table('parent_guardians')->insertGetId([
        'user_id' => $parent->id, 'first_name' => 'Adam', 'last_name' => 'Sana',
        'phone' => '7770009', 'email' => 'adam.sana@example.test',
        'address' => 'Malé', 'relationship' => 'father',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('guardian_student')->insert([
        'guardian_id' => $guardianId, 'student_id' => $student->id,
        'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($parent->fresh())
        ->post(route('portal.absence-notes.store'), [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'reason' => 'Posted the old way',
            'type' => 'illness',
        ])->assertRedirect();

    $note = AbsenceNote::query()->where('student_id', $student->id)->firstOrFail();

    // Resolved to the row, not left dangling on a string.
    expect($note->absence_type_id)->not->toBeNull()
        ->and(AbsenceType::query()->whereKey($note->absence_type_id)->value('code'))->toBe('illness');

    // And a note with no reason at all is refused rather than defaulted.
    $this->withoutLocalizationMiddleware()->actingAs($parent->fresh())
        ->post(route('portal.absence-notes.store'), [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'reason' => 'No reason chosen',
        ])->assertSessionHasErrors('absence_type_id');

    unset($staff);
});

it('walks the admin screen and drives the family form from the database', function () {
    ['staff' => $staff] = absenceTypeSetup();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.absence-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Academics/AbsenceTypes/Index')->has('types', 5)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.absence-types.store'), ['name' => 'Bereavement', 'requires_evidence' => false])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.absence-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('types', 6)->etc());

    $created = AbsenceType::query()->where('code', 'bereavement')->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->put(route('academics.absence-types.update', $created->id), [
            'name' => 'Bereavement leave', 'excuses_absence' => true, 'is_active' => true,
        ])->assertSessionHasNoErrors();

    expect($created->refresh()->name)->toBe('Bereavement leave');
});
