<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\Notifications\Actions\ListMessageRecipientsAction;
use App\Domains\Notifications\Actions\MarkMessageThreadReadAction;
use App\Domains\Notifications\Actions\ReplyToMessageThreadAction;
use App\Domains\Notifications\Actions\ShowMessageThreadAction;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageThread;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E2a — the messaging core loop. `messages` existed as a table with no reader;
 * these tests cover the thread layer that gives it a conversation, an inbox and
 * a reply policy.
 */
function seedFamilyAndTeacher(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $teacher = makeTeacherRow();
    app(\App\Domains\Academics\Actions\SaveTimetableEntryAction::class)->execute([
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
        'student' => $student,
        'studentUser' => User::query()->find($student->user_id),
        'teacher' => $teacher,
        'teacherUser' => User::query()->find($teacher->user_id),
        'class' => $class,
        'year' => $year,
    ];
}

it('offers the teachers who teach the student class as recipients', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    $recipients = app(ListMessageRecipientsAction::class)->execute((int) $studentUser->id);

    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()['user_id'])->toBe((int) $teacherUser->id)
        ->and(app(ListMessageRecipientsAction::class)->allows((int) $studentUser->id, (int) $teacherUser->id))
        ->toBeTrue();
});

it('offers nobody to a user with no student and no children', function () {
    $stranger = User::factory()->create();

    expect(app(ListMessageRecipientsAction::class)->execute((int) $stranger->id))->toBeEmpty();
});

it('does not offer a teacher who teaches some other class', function () {
    ['studentUser' => $studentUser] = seedFamilyAndTeacher();

    // A second class with its own teacher: reachable only by its own families.
    $otherYear = makeYear(['name' => '2027-2028']);
    $otherClass = makeClass($otherYear, 'Grade 2', 'B');
    $otherTeacher = makeTeacherRow();
    app(\App\Domains\Academics\Actions\SaveTimetableEntryAction::class)->execute([
        'class_id' => $otherClass->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $otherTeacher->id,
        'academic_year_id' => $otherYear->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow('10:00:00', '10:45:00', 3)->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    $ids = app(ListMessageRecipientsAction::class)->execute((int) $studentUser->id)->pluck('user_id');

    expect($ids)->not->toContain((int) $otherTeacher->user_id);
});

it('starts a thread that reaches the recipient inbox unread', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Aisha will miss the first period.',
    );

    $inbox = app(ListMessageInboxAction::class)->execute((int) $teacherUser->id);

    expect($inbox)->toHaveCount(1)
        ->and($inbox->first()['subject'])->toBe('About Sunday')
        ->and($inbox->first()['unread'])->toBe(1)
        ->and($inbox->first()['with'])->toBe([$studentUser->name])
        ->and(app(ListMessageInboxAction::class)->unreadCount((int) $teacherUser->id))->toBe(1);
});

it('does not count the author own message as unread for them', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Aisha will miss the first period.',
    );

    expect(app(ListMessageInboxAction::class)->unreadCount((int) $studentUser->id))->toBe(0)
        ->and(app(ListMessageInboxAction::class)->execute((int) $studentUser->id)->first()['unread'])->toBe(0);
});

it('refuses a thread with no recipient other than the author', function () {
    $user = User::factory()->create();

    app(StartMessageThreadAction::class)->execute((int) $user->id, [(int) $user->id], 'Hi', 'Hello');
})->throws(ValidationException::class);

it('clears unread when the thread is opened', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    $thread = app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Aisha will miss the first period.',
    );

    app(MarkMessageThreadReadAction::class)->execute((int) $thread->id, (int) $teacherUser->id);

    expect(app(ListMessageInboxAction::class)->unreadCount((int) $teacherUser->id))->toBe(0)
        // The legacy per-message flag is the other source of truth; leaving it
        // stale would make two parts of the app disagree about "unread".
        ->and(Message::query()->where('thread_id', $thread->id)->where('is_read', false)->count())->toBe(0);
});

it('shows the conversation in order to a participant and hides it from anyone else', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    $thread = app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Aisha will miss the first period.',
    );
    app(ReplyToMessageThreadAction::class)->execute((int) $thread->id, (int) $teacherUser->id, 'Noted, thank you.');

    $view = app(ShowMessageThreadAction::class)->execute((int) $thread->id, (int) $studentUser->id);

    expect($view['messages'])->toHaveCount(2)
        ->and($view['messages'][0]['body'])->toBe('Aisha will miss the first period.')
        ->and($view['messages'][0]['is_mine'])->toBeTrue()
        ->and($view['messages'][1]['body'])->toBe('Noted, thank you.')
        ->and($view['messages'][1]['sender'])->toBe($teacherUser->name)
        ->and($view['can_reply'])->toBeTrue();

    $stranger = User::factory()->create();
    expect(app(ShowMessageThreadAction::class)->execute((int) $thread->id, (int) $stranger->id))->toBeNull();
});

it('refuses a reply from someone outside the thread', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();
    $thread = app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Body',
    );

    app(ReplyToMessageThreadAction::class)->execute(
        (int) $thread->id,
        (int) User::factory()->create()->id,
        'Let me in',
    );
})->throws(ValidationException::class);

it('turns reply-all off once a thread is wide', function () {
    $author = User::factory()->create();
    $recipients = User::factory()->count(6)->create()->pluck('id')->map(fn ($id): int => (int) $id)->all();

    $thread = app(StartMessageThreadAction::class)->execute($author->id, $recipients, 'Trip', 'Details');

    expect($thread->reply_policy)->toBe('author_only');

    // A recipient can still answer — privately, to the author. Only the author
    // reaches everybody.
    app(ReplyToMessageThreadAction::class)->execute((int) $thread->id, $recipients[0], 'Question?');

    $delivered = Message::query()
        ->where('thread_id', $thread->id)
        ->where('sender_id', $recipients[0])
        ->pluck('recipient_id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    expect($delivered)->toBe([(int) $author->id]);

    app(ReplyToMessageThreadAction::class)->execute((int) $thread->id, (int) $author->id, 'Answer.');

    $fromAuthor = Message::query()
        ->where('thread_id', $thread->id)
        ->where('sender_id', $author->id)
        ->where('content', 'Answer.')
        ->count();

    expect($fromAuthor)->toBe(6);
});

it('keeps reply-all on for a small thread', function () {
    $author = User::factory()->create();
    $recipients = User::factory()->count(2)->create()->pluck('id')->map(fn ($id): int => (int) $id)->all();

    $thread = app(StartMessageThreadAction::class)->execute($author->id, $recipients, 'Trip', 'Details');
    expect($thread->reply_policy)->toBe('all');

    app(ReplyToMessageThreadAction::class)->execute((int) $thread->id, $recipients[0], 'Me too');

    $delivered = Message::query()
        ->where('thread_id', $thread->id)
        ->where('sender_id', $recipients[0])
        ->pluck('recipient_id')
        ->map(fn ($id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($delivered)->toBe(collect([$author->id, $recipients[1]])->map(fn ($id): int => (int) $id)->sort()->values()->all());
});

it('refuses every reply when the policy is none', function () {
    $author = User::factory()->create();
    $recipient = User::factory()->create();

    $thread = app(StartMessageThreadAction::class)->execute(
        (int) $author->id,
        [(int) $recipient->id],
        'Notice',
        'Read only',
        ['reply_policy' => 'none'],
    );

    expect($thread->reply_policy)->toBe('none')
        ->and(app(ShowMessageThreadAction::class)->execute((int) $thread->id, (int) $recipient->id)['can_reply'])
        ->toBeFalse();

    app(ReplyToMessageThreadAction::class)->execute((int) $thread->id, (int) $recipient->id, 'Anyway');
})->throws(ValidationException::class);

it('stores a context as a morph alias, never an FQCN', function () {
    $author = User::factory()->create();
    $recipient = User::factory()->create();

    $thread = app(StartMessageThreadAction::class)->execute(
        (int) $author->id,
        [(int) $recipient->id],
        'Class notice',
        'Body',
        ['context_type' => 'classroom', 'context_id' => 7],
    );

    expect(MessageThread::query()->find($thread->id)->context_type)->toBe('classroom')
        ->and($thread->context_type)->not->toContain('App\\Domains');
});

it('lets a guardian write to their child teachers', function () {
    ['student' => $student, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father');

    $recipients = app(ListMessageRecipientsAction::class)->execute((int) $guardian->user_id);

    expect($recipients->pluck('user_id')->all())->toContain((int) $teacherUser->id);
});
