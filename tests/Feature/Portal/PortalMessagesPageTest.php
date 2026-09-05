<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * E2a walked end to end over HTTP: compose → send → the teacher's inbox → open
 * → reply → the family sees it. Passing actions in isolation is not the
 * definition of done; a user has to be able to complete the task.
 */
function seedMessagingPair(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $teacher = makeTeacherRow();
    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return [
        'family' => User::query()->find($student->user_id),
        'teacherUser' => User::query()->find($teacher->user_id),
    ];
}

it('walks compose, send, inbox, open and reply', function () {
    ['family' => $family, 'teacherUser' => $teacherUser] = seedMessagingPair();

    $this->withoutLocalizationMiddleware()
        ->actingAs($family)
        ->get('/portal/messages/new')
        ->assertOk();

    $this->withoutLocalizationMiddleware()
        ->actingAs($family)
        ->post('/portal/messages', [
            'recipient_id' => $teacherUser->id,
            'subject' => 'About Sunday',
            'body' => 'Aisha will miss the first period.',
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get('/portal/messages')
        ->assertOk()
        ->assertSee('About Sunday');

    $threadId = \App\Domains\Notifications\Models\MessageThread::query()->value('id');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get('/portal/messages/'.$threadId)
        ->assertOk()
        ->assertSee('Aisha will miss the first period.');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->post('/portal/messages/'.$threadId.'/reply', ['body' => 'Noted, thank you.'])
        ->assertRedirect('/portal/messages/'.$threadId);

    $this->withoutLocalizationMiddleware()
        ->actingAs($family)
        ->get('/portal/messages/'.$threadId)
        ->assertOk()
        ->assertSee('Noted, thank you.');
});

it('refuses to send to someone outside the directory', function () {
    ['family' => $family] = seedMessagingPair();
    $stranger = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs($family)
        ->post('/portal/messages', [
            'recipient_id' => $stranger->id,
            'subject' => 'Hello',
            'body' => 'Body',
        ])
        ->assertForbidden();
});

it('refuses to show a thread the viewer is not part of', function () {
    ['family' => $family, 'teacherUser' => $teacherUser] = seedMessagingPair();

    $thread = app(StartMessageThreadAction::class)->execute(
        (int) $family->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Body',
    );

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get('/portal/messages/'.$thread->id)
        ->assertForbidden();
});

it('keeps the compose route from being swallowed by the thread matcher', function () {
    ['family' => $family] = seedMessagingPair();

    // /portal/messages/new must not resolve as thread "new"; a numeric
    // constraint on {thread} is what keeps the two apart.
    $this->withoutLocalizationMiddleware()
        ->actingAs($family)
        ->get('/portal/messages/new')
        ->assertOk();
});
