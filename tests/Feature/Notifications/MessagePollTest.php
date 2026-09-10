<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\RespondToMessagePollAction;
use App\Domains\Notifications\Actions\ShowMessageThreadAction;
use App\Domains\Notifications\Actions\StartClassMessageThreadAction;
use App\Domains\Notifications\Models\MessagePoll;
use App\Domains\Notifications\Models\MessagePollResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E2b-b — a thread can carry a question.
 *
 * "Will your child attend the trip?" is what schools actually want from
 * messaging. The plan puts simple polls here rather than in E6's form builder:
 * the audience, delivery and reply policy already exist on a thread.
 */
function askClass(array $seed, array $poll = ['question' => 'Attending the trip?', 'options' => ['Yes', 'No']])
{
    return app(StartClassMessageThreadAction::class)->execute(
        (int) $seed['teacherUser']->id,
        (int) $seed['class']->id,
        'School trip',
        'Details attached.',
        'guardians',
        $poll,
    );
}

it('attaches a question to a class broadcast', function () {
    $seed = seedClassWithFamilies(3);
    $thread = askClass($seed);

    $poll = MessagePoll::query()->where('message_thread_id', $thread->id)->first();

    expect($poll)->not->toBeNull()
        ->and($poll->question)->toBe('Attending the trip?')
        ->and($poll->options)->toBe(['Yes', 'No'])
        ->and($poll->isOpen())->toBeTrue()
        // Rule 10: time-scoped, carrying the year it was asked in.
        ->and($poll->academic_year_id)->not->toBeNull();
});

it('sends no poll when the compose form left it blank', function () {
    $seed = seedClassWithFamilies(2);
    app(StartClassMessageThreadAction::class)->execute(
        (int) $seed['teacherUser']->id,
        (int) $seed['class']->id,
        'Reminder',
        'No question here.',
    );

    expect(MessagePoll::query()->count())->toBe(0);
});

it('refuses a question with fewer than two options', function () {
    $seed = seedClassWithFamilies(2);

    // One option is not a choice, and an unanswerable question sent to every
    // family is worse than no question.
    askClass($seed, ['question' => 'Coming?', 'options' => ['Yes']]);
})->throws(ValidationException::class);

it('refuses a question with no text', function () {
    $seed = seedClassWithFamilies(2);

    askClass($seed, ['question' => '   ', 'options' => ['Yes', 'No']]);
})->throws(ValidationException::class);

it('records a recipients answer', function () {
    $seed = seedClassWithFamilies(3);
    $thread = askClass($seed);
    $guardian = $seed['guardians'][0];

    app(RespondToMessagePollAction::class)->execute((int) $thread->id, (int) $guardian->user_id, 0);

    expect(MessagePollResponse::query()->count())->toBe(1)
        ->and((int) MessagePollResponse::query()->first()->choice)->toBe(0);
});

it('replaces an answer rather than counting it twice', function () {
    $seed = seedClassWithFamilies(3);
    $thread = askClass($seed);
    $guardian = $seed['guardians'][0];

    app(RespondToMessagePollAction::class)->execute((int) $thread->id, (int) $guardian->user_id, 0);
    app(RespondToMessagePollAction::class)->execute((int) $thread->id, (int) $guardian->user_id, 1);

    // A parent who mis-taps can correct it and the tally still means what it says.
    expect(MessagePollResponse::query()->count())->toBe(1)
        ->and((int) MessagePollResponse::query()->first()->choice)->toBe(1);
});

it('refuses an answer from outside the thread', function () {
    $seed = seedClassWithFamilies(2);
    $thread = askClass($seed);

    app(RespondToMessagePollAction::class)->execute((int) $thread->id, (int) User::factory()->create()->id, 0);
})->throws(ValidationException::class);

it('refuses a choice that is not on the list', function () {
    $seed = seedClassWithFamilies(2);
    $thread = askClass($seed);

    // Out of range would count towards a tally with no label.
    app(RespondToMessagePollAction::class)
        ->execute((int) $thread->id, (int) $seed['guardians'][0]->user_id, 7);
})->throws(ValidationException::class);

it('refuses an answer once the question has closed', function () {
    $seed = seedClassWithFamilies(2);
    $thread = askClass($seed);
    MessagePoll::query()->where('message_thread_id', $thread->id)
        ->update(['closes_at' => now()->subHour()]);

    app(RespondToMessagePollAction::class)
        ->execute((int) $thread->id, (int) $seed['guardians'][0]->user_id, 0);
})->throws(ValidationException::class);

it('shows tallies to the author and never to a recipient', function () {
    $seed = seedClassWithFamilies(3);
    $thread = askClass($seed);
    $guardians = $seed['guardians'];

    app(RespondToMessagePollAction::class)->execute((int) $thread->id, (int) $guardians[0]->user_id, 0);
    app(RespondToMessagePollAction::class)->execute((int) $thread->id, (int) $guardians[1]->user_id, 1);

    $reader = app(ShowMessageThreadAction::class);

    $authorView = $reader->execute((int) $thread->id, (int) $seed['teacherUser']->id)['poll'];
    expect($authorView['tallies'])->toBe([1, 1])
        ->and($authorView['responses'])->toBe(2);

    // On a class of twelve, "1 of 12 said no" identifies somebody. A recipient
    // sees their own answer and nothing else.
    $parentView = $reader->execute((int) $thread->id, (int) $guardians[0]->user_id)['poll'];
    expect($parentView)->not->toHaveKey('tallies')
        ->and($parentView)->not->toHaveKey('responses')
        ->and($parentView['my_choice'])->toBe(0);
});

it('reports no answer yet for someone who has not voted', function () {
    $seed = seedClassWithFamilies(2);
    $thread = askClass($seed);

    $view = app(ShowMessageThreadAction::class)
        ->execute((int) $thread->id, (int) $seed['guardians'][0]->user_id)['poll'];

    expect($view['my_choice'])->toBeNull()
        ->and($view['is_open'])->toBeTrue()
        ->and($view['options'])->toBe(['Yes', 'No']);
});

it('leaves poll null on a thread without one', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();
    $thread = app(\App\Domains\Notifications\Actions\StartMessageThreadAction::class)
        ->execute((int) $studentUser->id, [(int) $teacherUser->id], 'Hello', 'Body');

    expect(app(ShowMessageThreadAction::class)->execute((int) $thread->id, (int) $studentUser->id)['poll'])
        ->toBeNull();
});
