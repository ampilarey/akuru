<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageParticipant;
use Illuminate\Support\Facades\DB;

class MarkMessageThreadReadAction
{
    public function execute(int $threadId, int $userId): void
    {
        DB::transaction(function () use ($threadId, $userId): void {
            MessageParticipant::query()
                ->where('message_thread_id', $threadId)
                ->where('user_id', $userId)
                ->update(['last_read_at' => now()]);

            // The per-message flags predate threads and other code may still
            // read them; keep both truths aligned rather than leaving a second
            // stale source of "unread".
            Message::query()
                ->where('thread_id', $threadId)
                ->where('recipient_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        });
    }
}
