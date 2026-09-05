<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageParticipant;
use App\Domains\Notifications\Models\MessageThread;
use Illuminate\Support\Facades\DB;

/**
 * One thread as the reader sees it: the conversation in order, who is in it,
 * and whether this reader is allowed to reply.
 *
 * Returns null when the reader is not a participant, so membership is decided
 * once here rather than in every caller.
 */
class ShowMessageThreadAction
{
    /**
     * @return ?array<string, mixed>
     */
    public function execute(int $threadId, int $userId): ?array
    {
        $thread = MessageThread::query()->find($threadId);
        if ($thread === null) {
            return null;
        }

        $participants = MessageParticipant::query()
            ->where('message_thread_id', $thread->id)
            ->get();

        if (! $participants->contains(fn (MessageParticipant $row): bool => (int) $row->user_id === $userId)) {
            return null;
        }

        $names = $this->namesFor(
            $participants->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
        );

        // A thread fans out one row per recipient, so the same reply exists
        // several times. Collapsing on (sender, created_at, content) shows the
        // conversation once instead of once per person it was sent to.
        $messages = Message::query()
            ->where('thread_id', $thread->id)
            ->where(function ($query) use ($userId): void {
                $query->where(fn ($q) => $q->where('recipient_id', $userId)->where('is_deleted_by_recipient', false))
                    ->orWhere(fn ($q) => $q->where('sender_id', $userId)->where('is_deleted_by_sender', false));
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->unique(fn (Message $message): string => implode('|', [
                (int) $message->sender_id,
                (string) $message->created_at,
                (string) $message->content,
            ]))
            ->map(fn (Message $message): array => [
                'id' => (int) $message->id,
                'sender_id' => (int) $message->sender_id,
                'sender' => $names[(int) $message->sender_id] ?? 'Unknown',
                'is_mine' => (int) $message->sender_id === $userId,
                'body' => (string) $message->content,
                'is_important' => (bool) $message->is_important,
                'sent_at' => $message->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'id' => (int) $thread->id,
            'subject' => (string) $thread->subject,
            'reply_policy' => (string) $thread->reply_policy,
            'can_reply' => $thread->allowsReplyFrom($userId),
            // Under author_only a recipient still gets to answer — privately, to
            // the author. Saying so up front beats a reply that silently goes
            // somewhere the sender did not expect.
            'reply_goes_to_author_only' => ! $thread->allowsReplyToAll() && (int) $thread->created_by !== $userId,
            'participants' => $participants
                ->map(fn (MessageParticipant $row): array => [
                    'user_id' => (int) $row->user_id,
                    'name' => $names[(int) $row->user_id] ?? 'Unknown',
                    'role' => (string) $row->role,
                ])
                ->values()
                ->all(),
            'messages' => $messages,
        ];
    }

    /**
     * Names come from the users table directly rather than an Identity model
     * import: Notifications may not reach into Identity\Models (rule 3).
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    private function namesFor(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter($userIds)));
        if ($ids === []) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }
}
