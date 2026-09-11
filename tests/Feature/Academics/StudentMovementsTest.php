<?php

use App\Domains\Academics\Actions\ListGateMovementsAction;
use App\Domains\Academics\Actions\ListMovementsForGuardianAction;
use App\Domains\Academics\Actions\RecordStudentMovementAction;
use App\Domains\Academics\Actions\VoidStudentMovementAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\MovementDirection;
use App\Domains\Academics\Enums\MovementSource;
use App\Domains\Academics\Models\StudentMovement;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E18 — arrivals and departures.
 *
 * The plan's warning shapes these tests: *"do not build the software until the
 * hardware question is answered, or it will be a manual log nobody fills."*
 * Several assertions below exist to prove the log stays *fillable* — that the
 * action never dead-ends somebody standing at a gate.
 */
function gateSetup(): array
{
    makeYear(['name' => 'Gate year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);

    Role::findOrCreate('admin', 'web');
    $staff = User::factory()->create(['name' => 'Gate Staff']);
    $staff->assignRole('admin');

    return [
        'student' => makeStudent(['first_name' => 'Ibrahim', 'last_name' => 'Nasir']),
        'staff' => $staff->fresh(),
    ];
}

it('records an arrival and a departure, and derives who is inside', function () {
    ['student' => $student, 'staff' => $staff] = gateSetup();
    $record = app(RecordStudentMovementAction::class);

    $in = $record->execute($student->id, MovementDirection::In, $staff->id);
    expect($in->direction)->toBe(MovementDirection::In)
        ->and($in->source)->toBe(MovementSource::Manual)
        ->and((int) $in->recorded_by)->toBe((int) $staff->id);

    $lists = app(ListGateMovementsAction::class)->execute();
    expect($lists['in_count'])->toBe(1)->and($lists['out_count'])->toBe(0);

    // Far enough apart not to be a double tap.
    $this->travel(5)->minutes();
    $record->execute($student->id, MovementDirection::Out, $staff->id);

    $lists = app(ListGateMovementsAction::class)->execute();
    expect($lists['in_count'])->toBe(0)
        ->and($lists['out_count'])->toBe(1)
        ->and($lists['movements'])->toHaveCount(2);
});

it('never dead-ends the person at the gate', function () {
    // A child who goes to the dentist and comes back is in/out/in/out, and a
    // log that starts at 10am has an `out` with no `in`. Both are ordinary. A
    // system that refuses to record what somebody is looking at is the "manual
    // log nobody fills" the plan warns about.
    ['student' => $student, 'staff' => $staff] = gateSetup();
    $record = app(RecordStudentMovementAction::class);

    $record->execute($student->id, MovementDirection::Out, $staff->id);
    $this->travel(5)->minutes();
    $record->execute($student->id, MovementDirection::In, $staff->id);
    $this->travel(5)->minutes();
    $record->execute($student->id, MovementDirection::In, $staff->id);

    expect(StudentMovement::query()->count())->toBe(3);

    // But the console can warn, because it knows the current state.
    expect(app(ListGateMovementsAction::class)->currentStateFor([$student->id]))
        ->toBe([$student->id => 'in']);
});

it('treats the same direction twice within two minutes as one tap', function () {
    ['student' => $student, 'staff' => $staff] = gateSetup();
    $record = app(RecordStudentMovementAction::class);

    $first = $record->execute($student->id, MovementDirection::In, $staff->id);
    $second = $record->execute($student->id, MovementDirection::In, $staff->id);

    expect((int) $second->id)->toBe((int) $first->id)
        ->and(StudentMovement::query()->count())->toBe(1);

    // Past the window it is a real second movement, not a tap.
    $this->travel(3)->minutes();
    $third = $record->execute($student->id, MovementDirection::In, $staff->id);
    expect((int) $third->id)->not->toBe((int) $first->id);
});

it('takes a mistake back without deleting it', function () {
    ['student' => $student, 'staff' => $staff] = gateSetup();
    $movement = app(RecordStudentMovementAction::class)->execute($student->id, MovementDirection::Out, $staff->id);

    app(VoidStudentMovementAction::class)->execute($movement->id, $staff->id);

    // The row survives — a parent told their child left at 13:40 and later told
    // they did not is owed an explanation a deleted row cannot give.
    expect(StudentMovement::query()->count())->toBe(1)
        ->and(StudentMovement::query()->live()->count())->toBe(0);

    $lists = app(ListGateMovementsAction::class)->execute();
    expect($lists['movements'])->toHaveCount(1)
        ->and($lists['movements']->first()['voided'])->toBeTrue()
        ->and($lists['out_count'])->toBe(0);

    // And it cannot be taken back twice, so two people cannot both believe they
    // were the one who fixed it.
    expect(fn () => app(VoidStudentMovementAction::class)->execute($movement->id, $staff->id))
        ->toThrow(ValidationException::class);
});

it('hides taken-back rows from families but not from staff', function () {
    ['student' => $student, 'staff' => $staff] = gateSetup();
    $movement = app(RecordStudentMovementAction::class)->execute($student->id, MovementDirection::In, $staff->id);
    app(VoidStudentMovementAction::class)->execute($movement->id, $staff->id);

    // Staff need to see a correction was made; a parent needs to know where
    // their child is, and a list of retracted times answers a question they
    // did not ask.
    expect(app(ListMovementsForGuardianAction::class)->execute([$student->id]))->toHaveCount(0)
        ->and(app(ListGateMovementsAction::class)->execute()['movements'])->toHaveCount(1);
});

it('scopes a family to their own children', function () {
    ['student' => $mine, 'staff' => $staff] = gateSetup();
    $notMine = makeStudent(['first_name' => 'Aishath', 'last_name' => 'Waheed']);
    $record = app(RecordStudentMovementAction::class);

    $record->execute($mine->id, MovementDirection::In, $staff->id);
    $record->execute($notMine->id, MovementDirection::In, $staff->id);

    $rows = app(ListMovementsForGuardianAction::class)->execute([$mine->id]);

    expect($rows)->toHaveCount(1)->and($rows->first()['student'])->toBe('Ibrahim Nasir');
    expect(app(ListMovementsForGuardianAction::class)->execute([]))->toHaveCount(0);
});

it('carries the academic year and keeps days apart', function () {
    ['student' => $student, 'staff' => $staff] = gateSetup();
    $movement = app(RecordStudentMovementAction::class)->execute($student->id, MovementDirection::In, $staff->id);

    // Rule 10: a movement happens in time.
    expect((int) $movement->academic_year_id)->toBeGreaterThan(0);

    $yesterday = now()->subDay()->toDateString();
    expect(app(ListGateMovementsAction::class)->execute($yesterday)['movements'])->toHaveCount(0)
        ->and(app(ListGateMovementsAction::class)->execute()['movements'])->toHaveCount(1);
});

it('leaves room for a reader that has no user id', function () {
    // The hardware question the plan flags decides what presses the button,
    // not what the row looks like. A turnstile records the same fact with a
    // different source and no staff member behind it.
    ['student' => $student] = gateSetup();

    $movement = app(RecordStudentMovementAction::class)
        ->execute($student->id, MovementDirection::In, null, MovementSource::Card);

    expect($movement->recorded_by)->toBeNull()
        ->and($movement->source)->toBe(MovementSource::Card);

    $row = app(ListGateMovementsAction::class)->execute()['movements']->first();
    // The console names the device rather than blaming a member of staff who
    // was not there.
    expect($row['recorded_by'])->toBeNull()->and($row['source_label'])->toBe('Card');
});

it('walks both screens over http and keeps the gate console off the family side', function () {
    ['student' => $student, 'staff' => $staff] = gateSetup();

    $guardianUser = User::factory()->create(['name' => 'Nasir Ali']);
    $guardianId = DB::table('parent_guardians')->insertGetId([
        'user_id' => $guardianUser->id, 'first_name' => 'Nasir', 'last_name' => 'Ali',
        'phone' => '7770002', 'email' => 'nasir.ali@example.test',
        'address' => 'Ma. Kurangi', 'relationship' => 'father',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('guardian_student')->insert([
        'guardian_id' => $guardianId, 'student_id' => $student->id,
        'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.gate.record'), ['student_id' => $student->id, 'direction' => 'in'])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.gate.index', ['q' => 'Ibrahim']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Academics/Gate/Console')
            ->has('movements', 1)
            ->has('matches', 1)
            // The operator sees the child is already in before tapping again.
            ->where('matches.0.current', 'in')
            ->etc());

    $this->withoutLocalizationMiddleware()->actingAs($guardianUser)
        ->get(route('portal.movements'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Movements')->has('movements', 1)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($guardianUser)
        ->get(route('academics.gate.index'))->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($guardianUser)
        ->post(route('academics.gate.record'), ['student_id' => $student->id, 'direction' => 'out'])
        ->assertForbidden();
});
