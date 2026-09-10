<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\MessageThread;

/**
 * Tell the people a message was sent to that it exists.
 *
 * E2a and E2b shipped delivery without any announcement: a teacher could
 * broadcast to thirty families and none of them would be told. The only hint
 * was the unread badge on the portal home, which requires the family to visit
 * first — a notice about tomorrow is no use discovered next week.
 *
 * In-app only, deliberately. SMS costs real money per message and is gated on
 * `APP_ENV=production` plus an explicit `SMS_LIVE` (#86); a class broadcast is
 * exactly the shape of feature that turns a wiring mistake into a bill, so
 * choosing to send SMS for messages stays an owner decision.
 */
class NotifyMessageRecipientsAction
{
    /**
     * @param  list<int>  $recipientIds  exactly who this message reached — the
     *                                   thread actions compute the audience,
     *                                   including the author-only case, and the
     *                                   notification must not widen it
     */
    public function execute(MessageThread $thread, int $senderId, array $recipientIds, string $preview): void
    {
        $notifier = app(SendUserNotificationAction::class);

        foreach (array_unique($recipientIds) as $recipientId) {
            // Writing is not being told about your own writing.
            if ((int) $recipientId === $senderId) {
                continue;
            }

            $notifier->execute(
                (int) $recipientId,
                (string) $thread->subject,
                mb_substr($preview, 0, 160),
                [
                    'category' => 'message',
                    'href' => '/portal/messages/'.$thread->id,
                    'thread_id' => (int) $thread->id,
                ],
            );
        }
    }
}
