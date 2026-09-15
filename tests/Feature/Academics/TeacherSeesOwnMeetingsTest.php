<?php

use App\Domains\Academics\Actions\BookMeetingSlotAction;
use App\Domains\Academics\Actions\SaveMeetingSlotAction;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A teacher can see who booked their own parent-teacher meetings.
 *
 * ## What was missing
 *
 * `meeting_slots.teacher_id` names the teacher, and the family is shown that
 * name on the slot before booking it. Both ends knew whose meeting it was, and
 * **the teacher had nowhere to see one**: `/academics/meetings` is gated on
 * `meetings.manage`, which `admin`, `headmaster`, `supervisor` and
 * `super_admin` hold and `teacher` does not, while `/teach/schedule` lists
 * course sessions and `/portal/teacher` never mentioned meetings.
 *
 * Found by walking it — office publishes, family books, office sees the
 * booking, teacher looks in all three places and finds nothing (STATUS §5ec).
 *
 * ## Why this is a reader and not a permission
 *
 * The review-queue gap found the same day (KNOWN_ISSUES #28) could not be
 * closed this way: `course_instructor` has no rows and no writer, so "my
 * courses" cannot be expressed and somebody has to decide whether every teacher
 * sees every pupil's work. Here the scope is a column on the row. The last two
 * tests below are the ones that matter — a teacher sees **their own** slots,
 * and a signed-in user with no teacher row is refused outright.
 */
function meetingFixture(): array
{
    $admin = actingPeopleAdmin(['meetings.manage']);
    $year = makeYear(['name' => '2027-2028', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();

    $student = makeStudent(['first_name' => 'Booked', 'last_name' => 'Child']);
    app(App\Domains\Academics\Actions\AssignStudentToClassAction::class)->execute($class, $student->id);

    $guardianUser = User::factory()->create();
    $guardian = makeGuardian();
    App\Domains\People\Models\ParentGuardian::query()->whereKey($guardian->id)->update(['user_id' => $guardianUser->id]);
    app(AttachGuardianAction::class)->execute($student, $guardian->refresh(), 'mother', true);

    $slot = app(SaveMeetingSlotAction::class)->execute([
        'academic_year_id' => $year->id,
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'title' => 'Parent-teacher meeting',
        'date' => now()->addDay()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '18:10',
        'slot_minutes' => 10,
        'capacity' => 1,
        'status' => 'published',
        'created_by' => $admin->id,
    ]);

    $slot = is_iterable($slot) ? collect($slot)->first() : $slot;

    return compact('year', 'class', 'teacher', 'student', 'guardianUser', 'slot');
}

it('shows a teacher who booked their own meeting', function () {
    ['teacher' => $teacher, 'student' => $student, 'guardianUser' => $guardianUser, 'slot' => $slot] = meetingFixture();

    app(BookMeetingSlotAction::class)->execute(
        (int) $slot->id,
        (int) $student->id,
        (int) $guardianUser->id,
        'I would like to talk about reading.',
    );

    $teacherUser = User::query()->findOrFail($teacher->user_id);

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('teach.meetings'))
        ->assertOk();

    $slots = collect($response->viewData('page')['props']['slots']);
    $row = $slots->firstWhere('id', $slot->id);

    // The family's name is the point of the page. Asserting a row count would
    // pass on a screen that shows the teacher an empty diary.
    expect($row)->not->toBeNull()
        ->and($row['booked'])->toBe(1)
        ->and(collect($row['bookings'])->pluck('student_name')->all())->toContain('Booked Child');
});

it('shows a teacher nothing about another teacher\'s meetings', function () {
    ['student' => $student, 'guardianUser' => $guardianUser, 'slot' => $slot] = meetingFixture();

    app(BookMeetingSlotAction::class)->execute(
        (int) $slot->id,
        (int) $student->id,
        (int) $guardianUser->id,
    );

    // A second teacher, with a login and no part in that meeting.
    $other = makeTeacherRow();
    $otherUser = User::query()->findOrFail($other->user_id);

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->get(route('teach.meetings'))
        ->assertOk();

    $slots = collect($response->viewData('page')['props']['slots']);

    // The whole list, not just "the slot is absent": a page leaking one row
    // leaks the family's name on it.
    expect($slots)->toBeEmpty();
});

it('does not show a teacher a slot the office has not published', function () {
    ['year' => $year, 'class' => $class, 'teacher' => $teacher] = meetingFixture();

    $draft = app(SaveMeetingSlotAction::class)->execute([
        'academic_year_id' => $year->id,
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'title' => 'Draft meeting',
        'date' => now()->addDays(2)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '09:10',
        'slot_minutes' => 10,
        'capacity' => 1,
        'status' => 'draft',
    ]);
    $draft = is_iterable($draft) ? collect($draft)->first() : $draft;

    $teacherUser = User::query()->findOrFail($teacher->user_id);

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('teach.meetings'))
        ->assertOk();

    $ids = collect($response->viewData('page')['props']['slots'])->pluck('id')->all();

    // A draft is the office's working copy. Telling a teacher about a meeting
    // that may never be offered is worse than telling them nothing.
    expect($ids)->not->toContain($draft->id);
});

it('refuses a signed-in user who is not a teacher', function () {
    meetingFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('teach.meetings'))
        ->assertForbidden();
});

it('exports the same rows as a CSV', function () {
    ['teacher' => $teacher, 'student' => $student, 'guardianUser' => $guardianUser, 'slot' => $slot] = meetingFixture();

    app(BookMeetingSlotAction::class)->execute(
        (int) $slot->id,
        (int) $student->id,
        (int) $guardianUser->id,
    );

    $teacherUser = User::query()->findOrFail($teacher->user_id);

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('teach.meetings.export'))
        ->assertOk();

    expect($response->streamedContent())->toContain('Booked Child');
});
