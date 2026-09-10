<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\MessageParticipant;
use App\Domains\Notifications\Models\MessagePoll;
use App\Domains\Notifications\Models\MessagePollResponse;
use Illuminate\Validation\ValidationException;

/**
 * One person answers the question on a thread.
 *
 * Answering again replaces the previous answer rather than adding a second, so
 * a parent who mis-taps can correct it and the tally still means what it says.
 */
class RespondToMessagePollAction
{
    public function execute(int $threadId, int $userId, int $choice): MessagePollResponse
    {
        $poll = MessagePoll::query()->where('message_thread_id', $threadId)->first();

        if ($poll === null) {
            throw ValidationException::withMessages([
                'choice' => 'This message has no question to answer.',
            ]);
        }

        $isParticipant = MessageParticipant::query()
            ->where('message_thread_id', $threadId)
            ->where('user_id', $userId)
            ->exists();

        if (! $isParticipant) {
            throw ValidationException::withMessages([
                'choice' => 'You are not part of this conversation.',
            ]);
        }

        if (! $poll->isOpen()) {
            throw ValidationException::withMessages([
                'choice' => 'This question is closed.',
            ]);
        }

        // The choice is an index into the frozen options list; anything outside
        // it would count towards a tally with no label.
        if ($choice < 0 || $choice >= count($poll->options ?? [])) {
            throw ValidationException::withMessages([
                'choice' => 'That is not one of the options.',
            ]);
        }

        return MessagePollResponse::query()->updateOrCreate(
            ['message_poll_id' => $poll->id, 'user_id' => $userId],
            [
                'academic_year_id' => $poll->academic_year_id,
                'choice' => $choice,
                'responded_at' => now(),
            ],
        );
    }
}
