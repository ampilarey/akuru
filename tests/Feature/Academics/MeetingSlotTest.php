<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\BookMeetingSlotAction;
use App\Domains\Academics\Actions\GenerateMeetingSlotsAction;
use App\Domains\Academics\Actions\SaveMeetingSlotAction;
use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Models\MeetingBooking;
use App\Domains\Academics\Models\MeetingSlot;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Enums\GuardianRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function meetingAdmin(): \App\Domains\Identity\Models\User
{
    return actingPeopleAdmin(['meetings.manage']);
}

function meetingWindow(array $ctx, array $overrides = []): array
{
    return array_merge([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'teacher_id' => $ctx['teacher']->id,
        'class_id' => $ctx['class']->id,
        'title' => 'Term 1 PTM',
        'date' => now()->addWeek()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '19:00',
        'capacity' => 1,
        'status' => MeetingSlotStatus::Published->value,
    ], $overrides);
}

it('generates published slots, lists them, and exports csv', function () {
    $admin = meetingAdmin();
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 5', 'A');
    $teacher = makeTeacherRow();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.meetings.store'), meetingWindow(compact('year', 'term', 'class', 'teacher'), [
            'slot_minutes' => 30,
        ]))
        ->assertRedirect();

    expect(MeetingSlot::query()->count())->toBe(2)
        ->and(MeetingSlot::query()->first()->status)->toBe(MeetingSlotStatus::Published);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.meetings.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Meetings/Index')
            ->has('slots', 2)
            ->where('slots.0.title', 'Term 1 PTM')
            ->where('slots.0.start_time', '18:00')
            ->where('slots.1.start_time', '18:30')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.meetings.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Term 1 PTM')->and($csv)->toContain('18:00');
});

it('rejects overlapping slots for the same teacher', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $payload = meetingWindow(compact('year', 'term', 'class', 'teacher'), [
        'start_time' => '18:00',
        'end_time' => '18:20',
    ]);

    app(SaveMeetingSlotAction::class)->execute($payload);

    expect(fn () => app(SaveMeetingSlotAction::class)->execute($payload))
        ->toThrow(ValidationException::class);
});

it('lets a parent book a published class slot and rejects a full slot', function () {
    Role::findOrCreate('parent', 'web');
    $admin = meetingAdmin();
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 5', 'A');
    $teacher = makeTeacherRow();
    $student = makeStudent(['first_name' => 'Layla', 'last_name' => 'Hassan']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, now()->toDateString());

    $slots = app(GenerateMeetingSlotsAction::class)->execute(
        meetingWindow(compact('year', 'term', 'class', 'teacher'), ['slot_minutes' => 60]),
        $admin->id,
    );
    $slot = $slots[0];

    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, true);
    $guardianUser = User::query()->findOrFail($guardian->user_id);
    $guardianUser->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.meetings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Meetings')
            ->has('slots', 1)
            ->where('slots.0.remaining', 1)
            ->where('slots.0.can_book', true)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->post(route('portal.meetings.book', $slot->id), ['student_id' => $student->id])
        ->assertRedirect(route('portal.meetings'));

    expect(MeetingBooking::query()->where('status', 'booked')->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.meetings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('bookings', 1)
            ->where('bookings.0.student_name', 'Layla Hassan')
            ->where('slots.0.remaining', 0)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.meetings.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Layla Hassan')->and($csv)->toContain('Term 1 PTM');

    $other = makeStudent(['first_name' => 'Other']);
    app(AssignStudentToClassAction::class)->execute($class, $other->id, now()->toDateString());
    $otherGuardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($other, $otherGuardian, GuardianRelationship::Mother, true);
    $otherUser = User::query()->findOrFail($otherGuardian->user_id);
    $otherUser->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->post(route('portal.meetings.book', $slot->id), ['student_id' => $other->id])
        ->assertSessionHasErrors('slot');

    $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->post(route('portal.meetings.book', $slot->id), ['student_id' => $student->id])
        ->assertForbidden();
});

it('hides draft slots from the portal and cancels a booking', function () {
    Role::findOrCreate('parent', 'web');
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, $student->id, now()->toDateString());
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, true);
    $guardianUser = User::query()->findOrFail($guardian->user_id);
    $guardianUser->assignRole('parent');

    app(SaveMeetingSlotAction::class)->execute(meetingWindow(compact('year', 'term', 'class', 'teacher'), [
        'status' => MeetingSlotStatus::Draft->value,
        'start_time' => '18:00',
        'end_time' => '18:15',
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.meetings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('slots', 0));

    $published = app(SaveMeetingSlotAction::class)->execute(meetingWindow(compact('year', 'term', 'class', 'teacher'), [
        'start_time' => '19:00',
        'end_time' => '19:15',
    ]));

    app(BookMeetingSlotAction::class)->execute($published->id, $student->id, $guardianUser->id);

    $booking = MeetingBooking::query()->sole();
    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->post(route('portal.meetings.cancel', $booking->id))
        ->assertRedirect(route('portal.meetings'));

    expect($booking->fresh()->status->value)->toBe('cancelled');
});

it('forbids the admin meetings screen without meetings.manage', function () {
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.meetings.index'))
        ->assertForbidden();
});

it('does not import other domain models or Hifz from the portal meetings controller', function () {
    $src = file_get_contents(app_path('Domains/Portal/Http/Controllers/PortalMeetingController.php'));
    expect($src)->not->toContain('App\\Domains\\Hifz\\')
        ->and($src)->not->toMatch('/App\\\\Domains\\\\[A-Za-z]+\\\\Models\\\\/');
});
