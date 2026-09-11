<?php

use App\Domains\Academics\Actions\AdvancePickupNoticeAction;
use App\Domains\Academics\Actions\ListPickupNoticesAction;
use App\Domains\Academics\Actions\OpenPickupWindowAction;
use App\Domains\Academics\Actions\RequestPickupAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\GuardianMayCollectStudentAction;
use App\Domains\People\Actions\ListCollectableChildrenAction;
use App\Domains\People\Actions\SetPickupPinAction;
use App\Domains\People\Models\PickupPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E8 — student pick-up.
 *
 * **This releases a child.** The plan calls it "a protocol, not a button" and
 * says in terms: *do not ship steps 1–5 without step 2*. Step 2 is the PIN.
 * Almost every test here is a refusal, because on this module the refusals are
 * the feature.
 */
function pickupSetup(bool $canPickup = true): array
{
    makeYear(['name' => 'Pickup year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);

    $student = makeStudent(['first_name' => 'Fatima', 'last_name' => 'Yoosuf']);

    $guardianUser = User::factory()->create(['name' => 'Ahmed Yoosuf']);
    $guardianId = DB::table('parent_guardians')->insertGetId([
        'user_id' => $guardianUser->id,
        'first_name' => 'Ahmed', 'last_name' => 'Yoosuf',
        'phone' => '7770001', 'email' => 'ahmed.yoosuf@example.test',
        'address' => 'Ma. Fehi Aharu, Malé',
        'relationship' => 'father',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('guardian_student')->insert([
        'guardian_id' => $guardianId,
        'student_id' => $student->id,
        'relationship' => 'father',
        'is_primary' => true,
        'can_pickup' => $canPickup,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Role::findOrCreate('admin', 'web');
    $staff = User::factory()->create();
    $staff->assignRole('admin');

    return ['student' => $student, 'guardian' => $guardianUser, 'staff' => $staff->fresh()];
}

it('offers only children the guardian may collect, each with a name to read', function () {
    // Found in a browser, not by the suite: the dropdown listed every linked
    // child — including one this guardian may not collect — and every option
    // rendered blank because the shared children list has no `name` key.
    //
    // The gate would still have refused the extra child. But a parent at a
    // school gate being told "no" with no way to know why is the defect, and a
    // dropdown of empty rows is worse than an empty dropdown.
    ['student' => $student, 'guardian' => $guardian] = pickupSetup();

    $other = makeStudent(['first_name' => 'Hawwa', 'last_name' => 'Yoosuf']);
    DB::table('guardian_student')->insert([
        'guardian_id' => DB::table('parent_guardians')->where('user_id', $guardian->id)->value('id'),
        'student_id' => $other->id,
        'relationship' => 'father', 'is_primary' => false, 'can_pickup' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $children = app(ListCollectableChildrenAction::class)->execute($guardian->id);

    expect($children)->toHaveCount(1)
        ->and($children->first()['id'])->toBe((int) $student->id)
        ->and($children->first()['name'])->toBe('Fatima Yoosuf');

    // The list and the gate must answer the same question.
    expect(app(GuardianMayCollectStudentAction::class)->execute($guardian->id, $other->id))->toBeFalse();
});

it('refuses a request when the school has not opened pick-up', function () {
    // Step 1 is what makes this a protocol. Without it a guardian could ask at
    // 2am and the office would have no way to say "not yet".
    ['student' => $student, 'guardian' => $guardian] = pickupSetup();
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');

    expect(fn () => app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821'))
        ->toThrow(ValidationException::class);
});

it('refuses a guardian who may see the child but not collect them', function () {
    // Being allowed to read a child's attendance is not being allowed to take
    // them out of the building. can_pickup gets its first reader here.
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup(canPickup: false);
    app(OpenPickupWindowAction::class)->open($staff->id);
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');

    expect(fn () => app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821'))
        ->toThrow(ValidationException::class);
});

it('refuses a wrong PIN, and refuses a guardian with no PIN at all', function () {
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();
    app(OpenPickupWindowAction::class)->open($staff->id);

    // No PIN set: fails closed. There is no "no PIN means skip the check" path
    // — that is the fail-open shape §5bp already had to fix once.
    expect(fn () => app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821'))
        ->toThrow(ValidationException::class);

    app(SetPickupPinAction::class)->execute($guardian->id, '4821');

    expect(fn () => app(RequestPickupAction::class)->execute($guardian->id, $student->id, '9999'))
        ->toThrow(ValidationException::class);
});

it('refuses an obvious PIN', function () {
    ['guardian' => $guardian] = pickupSetup();

    foreach (['0000', '1111', '1234', '4321'] as $obvious) {
        expect(fn () => app(SetPickupPinAction::class)->execute($guardian->id, $obvious))
            ->toThrow(ValidationException::class);
    }

    foreach (['123', '123456789', 'abcd', '12a4'] as $malformed) {
        expect(fn () => app(SetPickupPinAction::class)->execute($guardian->id, $malformed))
            ->toThrow(ValidationException::class);
    }

    // A sensible one is accepted, and stored hashed.
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');
    $hash = PickupPin::query()->where('guardian_user_id', $guardian->id)->value('pin_hash');
    expect($hash)->not->toBe('4821')->and($hash)->not->toBeNull();
});

it('walks the whole protocol, and closes the loop', function () {
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();
    app(OpenPickupWindowAction::class)->open($staff->id);
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');

    // 2 + 3: guardian asks, office is told.
    $notice = app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821', 'Parking by the gate');
    expect($notice->status->value)->toBe('requested');

    $lists = app(ListPickupNoticesAction::class)->execute();
    expect($lists['waiting'])->toHaveCount(1)
        ->and($lists['left'])->toHaveCount(0)
        ->and($lists['waiting']->first()['student'])->toBe('Fatima Yoosuf')
        ->and($lists['waiting']->first()['note'])->toBe('Parking by the gate');

    // 4: staff send the child out. Still "waiting" to the office — a child at
    // reception has not gone yet.
    $notice = app(AdvancePickupNoticeAction::class)->send($notice, $staff->id);
    expect($notice->status->value)->toBe('sent')
        ->and((int) $notice->sent_by)->toBe((int) $staff->id)
        ->and(app(ListPickupNoticesAction::class)->execute()['waiting'])->toHaveCount(1);

    // 5: guardian confirms. Loop closed.
    $notice = app(AdvancePickupNoticeAction::class)->collect($notice, $guardian->id);
    expect($notice->status->value)->toBe('collected')
        ->and($notice->collected_at)->not->toBeNull();

    $lists = app(ListPickupNoticesAction::class)->execute();
    expect($lists['waiting'])->toHaveCount(0)->and($lists['left'])->toHaveCount(1);
});

it('refuses transitions that skip a step or repeat one', function () {
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();
    app(OpenPickupWindowAction::class)->open($staff->id);
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');
    $notice = app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821');

    // Collected without ever being sent would be, on paper, a child collected
    // who never left the classroom.
    expect(fn () => app(AdvancePickupNoticeAction::class)->collect($notice, $guardian->id))
        ->toThrow(ValidationException::class);

    $sent = app(AdvancePickupNoticeAction::class)->send($notice, $staff->id);

    // Two members of staff both believing they released a child is the exact
    // confusion this module exists to prevent.
    expect(fn () => app(AdvancePickupNoticeAction::class)->send($sent->fresh(), $staff->id))
        ->toThrow(ValidationException::class);

    // And a child already at reception cannot be quietly cancelled.
    expect(fn () => app(AdvancePickupNoticeAction::class)->cancel($sent->fresh()))
        ->toThrow(ValidationException::class);
});

it('lets only the guardian who asked close their own loop', function () {
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();
    app(OpenPickupWindowAction::class)->open($staff->id);
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');
    $notice = app(AdvancePickupNoticeAction::class)->send(
        app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821'),
        $staff->id,
    );

    $someoneElse = User::factory()->create();

    expect(fn () => app(AdvancePickupNoticeAction::class)->collect($notice, $someoneElse->id))
        ->toThrow(ValidationException::class);
});

it('does not create a second notice when a parent taps twice', function () {
    // A bad connection should not put the same child at reception twice.
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();
    app(OpenPickupWindowAction::class)->open($staff->id);
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');

    $first = app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821');
    $second = app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821');

    expect((int) $second->id)->toBe((int) $first->id)
        ->and(app(ListPickupNoticesAction::class)->execute()['waiting'])->toHaveCount(1);
});

it('expires by the day rather than by a cleanup job', function () {
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();
    app(OpenPickupWindowAction::class)->open($staff->id);
    app(SetPickupPinAction::class)->execute($guardian->id, '4821');
    app(RequestPickupAction::class)->execute($guardian->id, $student->id, '4821');

    // Yesterday is simply a different query, not stale data to clear up.
    $yesterday = now()->subDay()->toDateString();
    expect(app(ListPickupNoticesAction::class)->execute($yesterday)['waiting'])->toHaveCount(0)
        ->and(app(ListPickupNoticesAction::class)->execute()['waiting'])->toHaveCount(1);
});

it('walks both screens over http and refuses the console to a family', function () {
    ['student' => $student, 'guardian' => $guardian, 'staff' => $staff] = pickupSetup();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.pickup.open'))->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->post(route('portal.pickup.pin'), ['pin' => '4821'])->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->post(route('portal.pickup.request'), ['student_id' => $student->id, 'pin' => '4821'])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.pickup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Academics/Pickup/Console')->has('waiting', 1)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->get(route('portal.pickup'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Pickup')->has('notices', 1)->etc());

    // Step 4 and 5 over http, which is the only place `collectById` is used —
    // the portal reaches the notice by id because it may not name the model.
    $notice = app(ListPickupNoticesAction::class)->execute()['waiting']->first();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.pickup.send', $notice['id']))->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->post(route('portal.pickup.confirm', $notice['id']))->assertSessionHasNoErrors();

    expect(app(ListPickupNoticesAction::class)->execute()['left'])->toHaveCount(1);

    // A notice that does not exist is refused, not a 404 that confirms the
    // id space to somebody guessing.
    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->post(route('portal.pickup.confirm', 999999))->assertSessionHasErrors('pickup');

    // The office console is not a family screen.
    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->get(route('academics.pickup.index'))->assertForbidden();
});
