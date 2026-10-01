<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderComplaint;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P7a: *Report a problem* on an order. The customer
 * says what went wrong (and may add a photo); the office is told — by its
 * own email and phone too — and so is the shop; the office answers, moving
 * it to in progress or resolved, and the answer reaches the customer in the
 * app, by email and by SMS. The shop reads the problems on its own orders.
 */
class OrderComplaintAction
{
    public const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    public function report(int $userId, string $orderNumber, string $kind, string $body, ?UploadedFile $photo = null): OrderComplaint
    {
        $order = Order::query()->where('user_id', $userId)->where('number', $orderNumber)->firstOrFail();
        if (in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Expired], true)) {
            throw ValidationException::withMessages(['body' => __('shop.error_complaint_unpaid')]);
        }
        if (! in_array($kind, OrderComplaint::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('shop.error_complaint_kind')]);
        }
        $mediaId = $photo !== null ? (int) app(StorePrivateMediaAction::class)->execute($photo, $userId, self::PHOTO_MIMES, 10 * 1024 * 1024)['id'] : null;
        $complaint = OrderComplaint::query()->create([
            'order_id' => $order->id, 'vendor_id' => $order->vendor_id, 'user_id' => $userId,
            'kind' => $kind, 'body' => trim($body), 'photo_media_file_id' => $mediaId, 'status' => 'open',
        ]);

        $notify = app(NotifyBookshopUserAction::class);
        $title = __('shop.notice_complaint_title', ['number' => $order->number]);
        $message = __('shop.notice_complaint_body', ['number' => $order->number, 'kind' => __('shop.complaint_kind_'.$kind)]);
        $notify->office($title, $message, '/admin/bookshop/complaints', 'complaint');
        $notify->vendor((int) $order->vendor_id, $title, $message, '/vendor/orders', 'complaint');

        return $complaint;
    }

    /** The office answers; the customer hears it. */
    public function reply(int $complaintId, int $officeUserId, string $status, string $reply): OrderComplaint
    {
        if (! in_array($status, ['in_progress', 'resolved'], true) || trim($reply) === '') {
            throw ValidationException::withMessages(['reply' => __('shop.error_complaint_reply')]);
        }
        $complaint = OrderComplaint::query()->with('order:id,number,user_id')->findOrFail($complaintId);
        $complaint->update([
            'status' => $status, 'reply' => trim($reply), 'replied_by' => $officeUserId, 'replied_at' => now(),
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);
        app(NotifyBookshopUserAction::class)->execute(
            (int) $complaint->user_id,
            __('shop.notice_complaint_reply_title', ['number' => $complaint->order->number]),
            trim($reply),
            '/my-orders/'.$complaint->order->number,
            'complaint_reply',
        );

        return $complaint;
    }

    /**
     * The office's list, open first; or one shop's.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 200, ?int $vendorId = null): array
    {
        $query = OrderComplaint::query()->with(['order:id,number,user_id,address_snapshot', 'vendor:id,name'])
            ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")->orderByDesc('id');
        if ($vendorId !== null) {
            $query->where('vendor_id', $vendorId);
        }

        // Who to call back: the order's recipient, as the office reads it.
        return $query->limit($limit)->get()->map(fn (OrderComplaint $c) => $this->row($c) + [
            'recipient' => $c->order?->address_snapshot['recipient_name'] ?? null,
            'phone' => $c->order?->address_snapshot['phone'] ?? null,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function forOrder(int $orderId): array
    {
        return OrderComplaint::query()->where('order_id', $orderId)->orderBy('id')->get()->map(fn (OrderComplaint $c) => $this->row($c))->values()->all();
    }

    /** @return array<int, list<array<string, mixed>>> per order, for a shop's order list */
    public function forOrders(array $orderIds): array
    {
        return OrderComplaint::query()->whereIn('order_id', $orderIds)->orderBy('id')->get()
            ->groupBy('order_id')->map(fn ($rows) => $rows->map(fn (OrderComplaint $c) => $this->row($c))->values()->all())->all();
    }

    public function photo(int $complaintId): ?array
    {
        $complaint = OrderComplaint::query()->find($complaintId);

        return $complaint?->photo_media_file_id !== null ? app(ReadPrivateMediaAction::class)->execute((int) $complaint->photo_media_file_id) : null;
    }

    /** Who may open the photo: the customer who sent it, the shop's members, or the office (the controller checks the last). */
    public function parties(int $complaintId): array
    {
        $complaint = OrderComplaint::query()->findOrFail($complaintId);

        return ['user_id' => (int) $complaint->user_id, 'vendor_id' => (int) $complaint->vendor_id];
    }

    /** @return array<string, mixed> */
    private function row(OrderComplaint $c): array
    {
        return [
            'id' => $c->id,
            'number' => $c->order?->number,
            'shop' => $c->vendor?->name,
            'kind' => $c->kind,
            'body' => $c->body,
            'status' => $c->status,
            'reply' => $c->reply,
            'replied_at' => $c->replied_at?->format('Y-m-d H:i'),
            'created_at' => $c->created_at?->format('Y-m-d H:i'),
            'photo_url' => $c->photo_media_file_id !== null ? route('complaints.photo', $c->id) : null,
        ];
    }
}
