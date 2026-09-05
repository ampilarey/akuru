<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageParticipant;
use App\Domains\Notifications\Models\MessageThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartMessageThreadAction
{
    /**
     * @param  list<int>  $recipientIds
     * @param  array{context_type?: ?string, context_id?: ?int, reply_policy?: ?string, is_important?: bool}  $options
     */
    public function execute(
        int $authorId,
        array $recipientIds,
        string $subject,
        string $body,
        array $options = [],
    ): MessageThread {
        $recipients = array_values(array_unique(array_filter(
            array_map('intval', $recipientIds),
            fn (int $id): bool => $id > 0 && $id !== $authorId,
        )));

        if ($recipients === []) {
            throw ValidationException::withMessages([
                'recipients' => 'A thread needs at least one recipient other than you.',
            ]);
        }

        return DB::transaction(function () use ($authorId, $recipients, $subject, $body, $options): MessageThread {
            $thread = MessageThread::query()->create([
                'subject' => $subject,
                'created_by' => $authorId,
                'context_type' => $options['context_type'] ?? null,
                'context_id' => $options['context_id'] ?? null,
                'reply_policy' => $this->policyFor($recipients, $options['reply_policy'] ?? null),
                'last_message_at' => now(),
            ]);

            MessageParticipant::query()->create([
                'message_thread_id' => $thread->id,
                'user_id' => $authorId,
                'role' => 'author',
                // The author has read what they just wrote.
                'last_read_at' => now(),
            ]);

            foreach ($recipients as $recipientId) {
                MessageParticipant::query()->create([
                    'message_thread_id' => $thread->id,
                    'user_id' => $recipientId,
                    'role' => 'participant',
                    'last_read_at' => null,
                ]);

                // One row per recipient keeps the existing single-recipient
                // messages table honest rather than redefining what a row means.
                Message::query()->create([
                    'thread_id' => $thread->id,
                    'sender_id' => $authorId,
                    'recipient_id' => $recipientId,
                    'subject' => $subject,
                    'content' => $body,
                    'is_important' => (bool) ($options['is_important'] ?? false),
                ]);
            }

            return $thread->fresh();
        });
    }

    /**
     * Reply-all is off by default once a thread is wide, copying EduPage's
     * default because it was earned against real schools: without it, one
     * message to every parent becomes a message *from* every parent.
     *
     * @param  list<int>  $recipients
     */
    private function policyFor(array $recipients, ?string $requested): string
    {
        if ($requested !== null && in_array($requested, ['all', 'author_only', 'none'], true)) {
            return $requested;
        }

        return count($recipients) > 5 ? 'author_only' : 'all';
    }
}
