<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListMessageInboxAction
{
    /**
     * Threads the user is in, unread first, then most recent.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $userId): Collection
    {
        $participations = MessageParticipant::query()
            ->where('user_id', $userId)
            ->with('thread')
            ->get()
            ->filter(fn (MessageParticipant $row): bool => $row->thread !== null);

        if ($participations->isEmpty()) {
            return collect();
        }

        $threadIds = $participations->pluck('message_thread_id')->all();

        // Unread is counted from messages addressed to this user, not from the
        // thread's own timestamp: a reply the user themselves sent must not
        // make their own thread look unread.
        $unread = Message::query()
            ->whereIn('thread_id', $threadIds)
            ->where('recipient_id', $userId)
            ->where('is_read', false)
            ->where('is_deleted_by_recipient', false)
            ->selectRaw('thread_id, COUNT(*) as total')
            ->groupBy('thread_id')
            ->pluck('total', 'thread_id');

        $latest = Message::query()
            ->whereIn('thread_id', $threadIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('thread_id');

        // "Who is this from" is what makes an inbox readable; a list of subject
        // lines alone forces the reader to open every row to find out.
        $others = MessageParticipant::query()
            ->whereIn('message_thread_id', $threadIds)
            ->where('user_id', '!=', $userId)
            ->get()
            ->groupBy('message_thread_id');

        $names = $this->namesFor(
            $others->flatten(1)->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
        );

        return $participations
            ->map(function (MessageParticipant $row) use ($unread, $latest, $others, $names): array {
                $thread = $row->thread;
                $last = $latest->get($thread->id)?->first();

                return [
                    'id' => (int) $thread->id,
                    'subject' => (string) $thread->subject,
                    'reply_policy' => (string) $thread->reply_policy,
                    'unread' => (int) ($unread[$thread->id] ?? 0),
                    'with' => $others->get($thread->id, collect())
                        ->map(fn (MessageParticipant $other): string => $names[(int) $other->user_id] ?? 'Unknown')
                        ->values()
                        ->all(),
                    'last_message_at' => $thread->last_message_at?->toIso8601String(),
                    'preview' => $last ? mb_substr((string) $last->content, 0, 120) : null,
                    'href' => '/portal/messages/'.$thread->id,
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => ($b['unread'] > 0 ? 1 : 0) <=> ($a['unread'] > 0 ? 1 : 0),
                fn (array $a, array $b): int => ($b['last_message_at'] ?? '') <=> ($a['last_message_at'] ?? ''),
            ])
            ->values();
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

    /** Badge count for the E1 home tile. */
    public function unreadCount(int $userId): int
    {
        return (int) Message::query()
            ->whereNotNull('thread_id')
            ->where('recipient_id', $userId)
            ->where('is_read', false)
            ->where('is_deleted_by_recipient', false)
            ->count();
    }
}
