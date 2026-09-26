<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Notifications\Actions\ReplyToMessageThreadAction;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use Illuminate\Validation\ValidationException;

/**
 * The customer and the shop talk about an order (BOOKSHOP_PLAN §4, §5:
 * "message the vendor", "message the customer") on Notifications' existing
 * message threads, so it lands in both people's Messages with its unread
 * badge. One thread per order, started by whoever writes first, with the
 * customer and every member of the shop in it; the order keeps only the
 * thread's id (rule 3). A shop member who joined after the thread began
 * starts a fresh one that includes them.
 *
 * Callers resolve the order first — the customer's own, or the vendor's
 * through its `VendorScope`.
 */
class SendOrderMessageAction
{
    public function execute(Order $order, int $senderId, string $body): int
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('shop.error_message_empty')]);
        }

        if ($order->message_thread_id !== null) {
            try {
                return app(ReplyToMessageThreadAction::class)->execute((int) $order->message_thread_id, $senderId, $body)->id;
            } catch (ValidationException $e) {
                if (! array_key_exists('thread', $e->errors())) {
                    throw $e;
                }
                // Not in the old thread (joined the shop later): start one that includes them.
            }
        }

        $people = VendorMember::query()->where('vendor_id', $order->vendor_id)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $people[] = (int) $order->user_id;

        $thread = app(StartMessageThreadAction::class)->execute(
            $senderId,
            array_values(array_diff(array_unique($people), [$senderId])),
            __('shop.message_subject', ['number' => $order->number]),
            $body,
            ['context_type' => 'order', 'context_id' => $order->id, 'reply_policy' => 'all'],
        );
        $order->update(['message_thread_id' => $thread->id]);

        return $thread->id;
    }
}
