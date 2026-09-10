<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Notifications\Models\MessagePoll;
use App\Domains\Notifications\Models\MessageThread;
use Illuminate\Validation\ValidationException;

/**
 * Attach a question to a thread.
 *
 * Options are stored as a plain list rather than their own table: a poll's
 * choices are only ever read together with the poll, and a `choice` on a
 * response is the index into that list. Rewording an option after answers exist
 * would silently change what people answered, so **options are frozen once the
 * poll is created** — there is no update path here on purpose.
 */
class AttachPollToThreadAction
{
    /**
     * @param  array{question?: ?string, options?: ?array<int, string>, closes_at?: ?string}  $poll
     */
    public function execute(MessageThread $thread, array $poll): MessagePoll
    {
        $question = trim((string) ($poll['question'] ?? ''));

        $options = array_values(array_filter(
            array_map(fn ($option): string => trim((string) $option), $poll['options'] ?? []),
            fn (string $option): bool => $option !== '',
        ));

        if ($question === '') {
            throw ValidationException::withMessages([
                'poll.question' => 'A poll needs a question.',
            ]);
        }

        // One option is not a choice, and an unanswerable question in a message
        // to thirty families is worse than no question.
        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'poll.options' => 'A poll needs at least two options.',
            ]);
        }

        if (count($options) > 10) {
            throw ValidationException::withMessages([
                'poll.options' => 'A poll can offer at most ten options.',
            ]);
        }

        return MessagePoll::query()->create([
            'message_thread_id' => $thread->id,
            // Rule 10: the poll is time-scoped, and the year comes from when it
            // was asked rather than whichever year happens to be current when
            // somebody answers.
            'academic_year_id' => app(ResolveAcademicYearForDateAction::class)
                ->execute(now()->timezone(config('app.timezone'))->toDateString())['id'] ?? null,
            'question' => $question,
            'options' => $options,
            'closes_at' => $poll['closes_at'] ?? null,
        ]);
    }
}
