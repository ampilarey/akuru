<?php

use App\Domains\Academics\Actions\ListClassesTaughtByUserAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\Notifications\Actions\ListMessageRecipientsAction;
use App\Domains\Notifications\Actions\ReplyToMessageThreadAction;
use App\Domains\Notifications\Actions\StartClassMessageThreadAction;
use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageThread;
use App\Domains\People\Actions\ListFamilyUserIdsForStudentsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E2b — a teacher writes to a class.
 *
 * This is the case StartMessageThreadAction's reply policy was written for and
 * could never reach: E2a's compose form passes exactly one recipient, so the
 * author-only default above five recipients had no way to fire in production.
 *
 * seedClassWithFamilies() lives in tests/Support/MessagingTestHelpers.php.
 */
it('offers a teacher the classes they teach, with the reach of each audience', function () {
    ['teacherUser' => $teacherUser, 'class' => $class] = seedClassWithFamilies(3);

    $classes = app(ListMessageRecipientsAction::class)->classes((int) $teacherUser->id);

    expect($classes)->toHaveCount(1)
        ->and($classes->first()['id'])->toBe((int) $class->id)
        ->and($classes->first()['students_on_roster'])->toBe(3)
        ->and($classes->first()['reach']['guardians'])->toBe(3)
        // makeStudent() creates a login, so the pupils are reachable too.
        ->and($classes->first()['reach']['students'])->toBe(3)
        ->and($classes->first()['reach']['both'])->toBe(6);
});

it('offers no classes to someone who teaches none', function () {
    seedClassWithFamilies(2);

    expect(app(ListMessageRecipientsAction::class)->classes((int) User::factory()->create()->id))
        ->toBeEmpty();
});

it('does not offer a class taught by a different teacher', function () {
    ['class' => $class] = seedClassWithFamilies(2);
    $other = makeTeacherRow();

    expect(app(ListClassesTaughtByUserAction::class)->allows((int) $other->user_id, (int) $class->id))
        ->toBeFalse();
});

it('includes a class the user is class teacher of but does not teach on the timetable', function () {
    ['class' => $class] = seedClassWithFamilies(2);

    // classes.class_teacher_id holds a users.id, timetables.teacher_id holds a
    // teachers.id — merging the two id spaces would pick the wrong person.
    $head = makeTeacherRow();
    $class->forceFill(['class_teacher_id' => $head->user_id])->save();

    expect(app(ListClassesTaughtByUserAction::class)->allows((int) $head->user_id, (int) $class->id))
        ->toBeTrue();
});

it('delivers a broadcast to every guardian and nobody else', function () {
    ['teacherUser' => $teacherUser, 'class' => $class, 'guardians' => $guardians, 'students' => $students] =
        seedClassWithFamilies(3);

    $thread = app(StartClassMessageThreadAction::class)->execute(
        (int) $teacherUser->id,
        (int) $class->id,
        'Parent evening',
        'Thursday at 7pm.',
    );

    $recipients = Message::query()->where('thread_id', $thread->id)
        ->pluck('recipient_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
    $expected = collect($guardians)->pluck('user_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

    expect($recipients)->toBe($expected)
        // Default audience is guardians, so pupils are not copied in.
        ->and($recipients)->not->toContain((int) $students[0]->user_id);

    foreach ($guardians as $guardian) {
        expect(app(ListMessageInboxAction::class)->unreadCount((int) $guardian->user_id))->toBe(1);
    }
});

it('sends to pupils when that is the chosen audience', function () {
    ['teacherUser' => $teacherUser, 'class' => $class, 'students' => $students, 'guardians' => $guardians] =
        seedClassWithFamilies(2);

    $thread = app(StartClassMessageThreadAction::class)->execute(
        (int) $teacherUser->id,
        (int) $class->id,
        'Bring your PE kit',
        'Tomorrow.',
        ListFamilyUserIdsForStudentsAction::AUDIENCE_STUDENTS,
    );

    $recipients = Message::query()->where('thread_id', $thread->id)->pluck('recipient_id')
        ->map(fn ($id): int => (int) $id)->all();

    // A kit reminder should not land in every parent's inbox.
    expect($recipients)->toContain((int) $students[0]->user_id)
        ->and($recipients)->not->toContain((int) $guardians[0]->user_id);
});

it('files the thread against the class as a morph alias', function () {
    ['teacherUser' => $teacherUser, 'class' => $class] = seedClassWithFamilies(2);

    $thread = app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Notice', 'Body');

    $stored = MessageThread::query()->find($thread->id);

    expect($stored->context_type)->toBe('class_room')
        ->and($stored->context_type)->not->toContain('App\\Domains')
        ->and((int) $stored->context_id)->toBe((int) $class->id);
});

it('turns reply-all off on a class big enough to need it', function () {
    // Six guardians is the first size past the threshold.
    ['teacherUser' => $teacherUser, 'class' => $class, 'guardians' => $guardians] =
        seedClassWithFamilies(6);

    $thread = app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Trip', 'Details');

    expect($thread->reply_policy)->toBe('author_only');

    // A parent's reply reaches the teacher alone — not 5 other families.
    app(ReplyToMessageThreadAction::class)
        ->execute((int) $thread->id, (int) $guardians[0]->user_id, 'Is transport included?');

    $delivered = Message::query()
        ->where('thread_id', $thread->id)
        ->where('sender_id', $guardians[0]->user_id)
        ->pluck('recipient_id')->map(fn ($id): int => (int) $id)->all();

    expect($delivered)->toBe([(int) $teacherUser->id]);
});

it('keeps reply-all on for a small class', function () {
    ['teacherUser' => $teacherUser, 'class' => $class] = seedClassWithFamilies(3);

    $thread = app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Reminder', 'Body');

    // Three families can talk to each other without drowning anyone.
    expect($thread->reply_policy)->toBe('all');
});

it('refuses a broadcast to a class the sender does not teach', function () {
    ['class' => $class] = seedClassWithFamilies(2);

    app(StartClassMessageThreadAction::class)
        ->execute((int) makeTeacherRow()->user_id, (int) $class->id, 'Hello', 'Body');
})->throws(ValidationException::class);

it('refuses a broadcast when nobody in the class has an account', function () {
    ['teacherUser' => $teacherUser, 'class' => $class] = seedClassWithFamilies(2, withGuardians: false);

    // Guardians only, and none of these pupils has one.
    app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Hello', 'Body');
})->throws(ValidationException::class);

it('hides a class nobody in it can be reached at', function () {
    ['teacherUser' => $teacherUser] = seedClassWithFamilies(0);

    // An empty roster is not a target; offering it would promise a delivery
    // that reaches no one.
    expect(app(ListMessageRecipientsAction::class)->classes((int) $teacherUser->id))->toBeEmpty();
});
