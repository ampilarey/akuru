<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageParticipant;
use App\Domains\Notifications\Models\MessageThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a thread id rather than a model so callers outside Notifications never
 * have to name MessageThread (rule 3), and so the membership check lives with
 * the domain that owns the data instead of being repeated in every controller.
 */
class ReplyToMessageThreadAction
{
    public function execute(int $threadId, int $senderId, string $body): MessageThread
    {
        $thread = MessageThread::query()->findOrFail($threadId);

        $participants = MessageParticipant::query()
            ->where('message_thread_id', $thread->id)
            ->get();

        if (! $participants->contains(fn (MessageParticipant $row): bool => (int) $row->user_id === $senderId)) {
            throw ValidationException::withMessages([
                'thread' => 'You are not part of this conversation.',
            ]);
        }

        if (! $thread->allowsReplyFrom($senderId)) {
            throw ValidationException::withMessages([
                'body' => 'Replies are turned off for this message.',
            ]);
        }

        // Under author_only a recipient answers the author alone; reply-all is
        // what turns a broadcast into hundreds of messages, so it is the part
        // the policy withholds — not the ability to respond at all.
        $audience = $thread->allowsReplyToAll() || (int) $thread->created_by === $senderId
            ? $participants->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
            : [(int) $thread->created_by];

        $audience = array_values(array_filter($audience, fn (int $id): bool => $id !== $senderId));

        if ($audience === []) {
            throw ValidationException::withMessages([
                'thread' => 'This conversation has nobody left to reply to.',
            ]);
        }

        return DB::transaction(function () use ($thread, $senderId, $body, $audience): MessageThread {
            foreach ($audience as $recipientId) {
                Message::query()->create([
                    'thread_id' => $thread->id,
                    'sender_id' => $senderId,
                    'recipient_id' => $recipientId,
                    'subject' => $thread->subject,
                    'content' => $body,
                ]);
            }

            $thread->update(['last_message_at' => now()]);

            // Writing is reading: the sender should not see their own reply as
            // unread the moment they send it.
            MessageParticipant::query()
                ->where('message_thread_id', $thread->id)
                ->where('user_id', $senderId)
                ->update(['last_read_at' => now()]);

            return $thread->fresh();
        });
    }
}
