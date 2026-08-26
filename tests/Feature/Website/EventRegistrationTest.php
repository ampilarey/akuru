<?php

use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\EnforceSeatLimitAction;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\Website\Actions\ConfirmEventRegistrationAction;
use App\Domains\Website\Actions\OpenEventSecondRoundAction;
use App\Domains\Website\Actions\RegisterForEventAction;
use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeSchoolEvent(array $overrides = []): Event
{
    $year = $overrides['academic_year_id'] ?? makeYear(['name' => '2026-2027 Event', 'is_current' => true, 'status' => 'active'])->id;

    return app(SaveEventAction::class)->execute(array_merge([
        'title' => 'Quran Club',
        'description' => 'Elective recitation club',
        'location' => 'Hall A',
        'start_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'end_date' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
        'type' => 'workshop',
        'status' => 'published',
        'registration_type' => 'required',
        'max_attendees' => 1,
        'min_attendees' => 2,
        'waitlist_enabled' => true,
        'requires_parent_confirmation' => true,
        'is_elective' => true,
        'is_public' => true,
        'academic_year_id' => $year,
    ], $overrides));
}

it('reuses the offering seat lock so a full event without waitlist rejects the second registrant', function () {
    expect(file_get_contents(app_path('Domains/Offerings/Actions/ReserveOfferingSeatAction.php')))
        ->toContain('EnforceSeatLimitAction');

    $event = makeSchoolEvent([
        'waitlist_enabled' => false,
        'requires_parent_confirmation' => false,
        'min_attendees' => null,
        'max_attendees' => 1,
    ]);
    $first = makeStudent(['first_name' => 'One']);
    $second = makeStudent(['first_name' => 'Two']);

    $reserved = app(RegisterForEventAction::class)->execute([
        'event_id' => $event->id,
        'student_id' => $first->id,
        'email' => 'one@example.com',
        'registration_source' => 'admin',
        'fallback_email' => 'one@example.com',
    ]);
    expect($reserved->status)->toBe('pending')
        ->and($reserved->academic_year_id)->not->toBeNull();

    expect(fn () => app(RegisterForEventAction::class)->execute([
        'event_id' => $event->id,
        'student_id' => $second->id,
        'email' => 'two@example.com',
        'registration_source' => 'admin',
        'fallback_email' => 'two@example.com',
    ]))->toThrow(ValidationException::class);

    expect(EventRegistration::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('waitlists the second registrant when the seat lock reports full and waitlist is on', function () {
    $event = makeSchoolEvent([
        'requires_parent_confirmation' => false,
        'max_attendees' => 1,
        'waitlist_enabled' => true,
    ]);
    $first = makeStudent(['first_name' => 'Seat']);
    $second = makeStudent(['first_name' => 'Wait']);

    app(RegisterForEventAction::class)->execute([
        'event_id' => $event->id,
        'student_id' => $first->id,
        'registration_source' => 'admin',
        'fallback_email' => 'seat@example.com',
    ]);

    $waitlisted = app(RegisterForEventAction::class)->execute([
        'event_id' => $event->id,
        'student_id' => $second->id,
        'registration_source' => 'admin',
        'fallback_email' => 'wait@example.com',
    ]);

    expect($waitlisted->status)->toBe('waitlisted')
        ->and($waitlisted->waitlist_position)->toBe(1)
        ->and($event->fresh()->current_attendees)->toBe(1);
});

it('keeps parent-pending registrations occupying a seat until confirmed', function () {
    $event = makeSchoolEvent(['max_attendees' => 2, 'waitlist_enabled' => false]);
    $student = makeStudent(['first_name' => 'Child']);

    $registration = app(RegisterForEventAction::class)->execute([
        'event_id' => $event->id,
        'student_id' => $student->id,
        'registration_source' => 'portal',
        'fallback_email' => 'parent@example.com',
    ]);

    expect($registration->status)->toBe('pending_parent')
        ->and($event->fresh()->current_attendees)->toBe(1);

    $confirmed = app(ConfirmEventRegistrationAction::class)->execute($registration->id);
    expect($confirmed->status)->toBe('confirmed')
        ->and($confirmed->confirmed_at)->not->toBeNull();
});

it('opens a second round and promotes waitlisted rows until the minimum is met', function () {
    $event = makeSchoolEvent([
        'max_attendees' => 3,
        'min_attendees' => 2,
        'requires_parent_confirmation' => false,
        'waitlist_enabled' => true,
    ]);
    $first = makeStudent(['first_name' => 'Filled']);
    $second = makeStudent(['first_name' => 'Promoted']);

    app(RegisterForEventAction::class)->execute([
        'event_id' => $event->id,
        'student_id' => $first->id,
        'registration_source' => 'admin',
        'fallback_email' => 'filled@example.com',
    ]);

    $waitlisted = EventRegistration::query()->create([
        'event_id' => $event->id,
        'student_id' => $second->id,
        'name' => 'Promoted Student',
        'email' => 'promoted@example.com',
        'status' => 'waitlisted',
        'waitlist_position' => 1,
        'registration_source' => 'admin',
    ]);

    $result = app(OpenEventSecondRoundAction::class)->execute($event->id);

    expect($result['promoted'])->toBe(1)
        ->and($waitlisted->fresh()->status)->toBe('confirmed')
        ->and($result['event']->second_round_opens_at)->not->toBeNull()
        ->and($event->fresh()->current_attendees)->toBe(2);
});

it('lets a parent register and confirm their child and forbids another child', function () {
    $mine = makeStudent(['first_name' => 'Mine']);
    $other = makeStudent(['first_name' => 'Other']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($mine, $guardian, 'father', true);
    $parent = User::query()->findOrFail($guardian->user_id);
    $event = makeSchoolEvent(['max_attendees' => 5, 'waitlist_enabled' => false]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('portal.events.register', $event), ['student_id' => $mine->id])
        ->assertRedirect(route('portal.events'));

    $registration = EventRegistration::query()->where('student_id', $mine->id)->sole();
    expect($registration->status)->toBe('pending_parent')
        ->and($registration->registration_source)->toBe('portal');

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('portal.events.confirm', $registration))
        ->assertRedirect(route('portal.events'));

    expect($registration->fresh()->status)->toBe('confirmed');

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('portal.events.register', $event), ['student_id' => $other->id])
        ->assertForbidden();
});

it('lets an admin create an event, export csv, and register a student through inertia', function () {
    $admin = actingPeopleAdmin(['events.manage']);
    $year = makeYear(['name' => 'Pilot Events', 'is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'AdminChild']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.events.store'), [
            'title' => 'Arabic Club',
            'title_ar' => 'نادي العربية',
            'location' => 'Room 2',
            'start_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->addHours(2)->format('Y-m-d H:i:s'),
            'type' => 'workshop',
            'status' => 'published',
            'registration_type' => 'required',
            'max_attendees' => 2,
            'min_attendees' => 2,
            'waitlist_enabled' => true,
            'requires_parent_confirmation' => true,
            'is_elective' => true,
            'is_public' => false,
            'academic_year_id' => $year->id,
        ])
        ->assertRedirect(route('academics.events.index'));

    $event = Event::query()->where('title', 'Arabic Club')->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Website/Events/Index')
            ->has('events', 1));

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.events.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Arabic Club')->toContain('نادي العربية');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.events.register', $event), ['student_id' => $student->id])
        ->assertRedirect(route('academics.events.show', $event));

    expect(EventRegistration::query()->where('event_id', $event->id)->sole()->status)->toBe('pending_parent');
});

it('uses EnforceSeatLimitAction outcomes for reserved vs waitlisted', function () {
    $event = makeSchoolEvent([
        'max_attendees' => 1,
        'waitlist_enabled' => true,
        'requires_parent_confirmation' => false,
    ]);

    $first = app(EnforceSeatLimitAction::class)->execute(
        'events',
        $event->id,
        'max_attendees',
        'event_registrations',
        'event_id',
        RegisterForEventAction::OCCUPYING_STATUSES,
        'waitlist_enabled',
        'This event has no remaining seats.',
    );
    expect($first['outcome'])->toBe(EnforceSeatLimitAction::OUTCOME_RESERVED);

    EventRegistration::query()->create([
        'event_id' => $event->id,
        'name' => 'Held',
        'email' => 'held@example.com',
        'status' => 'pending',
        'registration_source' => 'admin',
    ]);

    $second = app(EnforceSeatLimitAction::class)->execute(
        'events',
        $event->id,
        'max_attendees',
        'event_registrations',
        'event_id',
        RegisterForEventAction::OCCUPYING_STATUSES,
        'waitlist_enabled',
        'This event has no remaining seats.',
    );
    expect($second['outcome'])->toBe(EnforceSeatLimitAction::OUTCOME_WAITLISTED)
        ->and($second['waitlist_position'])->toBe(1);
});
