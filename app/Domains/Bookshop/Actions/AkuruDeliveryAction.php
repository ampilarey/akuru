<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Vendor\FulfilVendorOrderAction;
use App\Domains\Bookshop\Enums\DeliveryKind;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\DeliveryDriver;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderDelivery;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Support\AkuruFulfilment;
use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\Actions\ResolveUserByIdentifierAction;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P6b: Akuru's drivers deliver the orders Akuru
 * delivers. The office keeps the drivers and assigns an order; the driver,
 * on `/deliveries`, marks it picked up (the order is dispatched — "out for
 * delivery" — and the customer told) and then delivered with a photo from
 * the phone (the order is delivered, the customer told, the earning's
 * return window starts). Both steps go through the shop's own order
 * machine (`FulfilVendorOrderAction`) under the office's scope (ADR-042),
 * so there is one path to an order's state.
 */
class AkuruDeliveryAction
{
    public const ROLE = 'driver';

    public const PROOF_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    /**
     * @return list<array{id: int, user_id: int, name: string, phone: ?string, active: bool, open: int}>
     */
    public function drivers(): array
    {
        $open = OrderDelivery::query()->whereNull('delivered_at')->selectRaw('delivery_driver_id, count(*) as n')->groupBy('delivery_driver_id')->pluck('n', 'delivery_driver_id');

        return DeliveryDriver::query()->orderByDesc('is_active')->orderBy('name')->get()->map(fn (DeliveryDriver $d) => [
            'id' => $d->id, 'user_id' => (int) $d->user_id, 'name' => $d->name, 'phone' => $d->phone,
            'active' => (bool) $d->is_active, 'open' => (int) ($open[$d->id] ?? 0),
        ])->values()->all();
    }

    /**
     * Add a driver by email: an existing account gets the role; a new one is
     * made, with a one-time password the office hands over.
     *
     * @return array{driver: DeliveryDriver, temporary_password: ?string}
     */
    public function addDriver(string $email, string $name, ?string $phone): array
    {
        $email = strtolower(trim($email));
        $existing = app(ResolveUserByIdentifierAction::class)->execute($email);
        $password = null;
        if ($existing !== null) {
            $userId = (int) $existing->id;
            $userModel = config('auth.providers.users.model');
            $user = $userModel::query()->findOrFail($userId);
            if (! $user->hasRole(self::ROLE)) {
                $user->assignRole(self::ROLE);
            }
        } else {
            $password = Str::password(12, letters: true, numbers: true, symbols: false);
            $userId = (int) app(CreateUserAction::class)->execute(trim($name), $email, $password, $phone ?: null, self::ROLE, forcePasswordChange: true)['id'];
        }
        $driver = DeliveryDriver::query()->updateOrCreate(['user_id' => $userId], ['name' => trim($name), 'phone' => $phone ?: null, 'is_active' => true]);

        return ['driver' => $driver, 'temporary_password' => $password];
    }

    public function setActive(int $driverId, bool $active): DeliveryDriver
    {
        $driver = DeliveryDriver::query()->findOrFail($driverId);
        $driver->update(['is_active' => $active]);

        return $driver;
    }

    /** The office gives an order Akuru delivers to a driver (or to another, before pick-up). */
    public function assign(int $orderId, int $driverId, int $officeUserId): OrderDelivery
    {
        $order = Order::query()->findOrFail($orderId);
        if ($order->delivery_kind !== DeliveryKind::AkuruCourier || ! in_array($order->status, [OrderStatus::Paid, OrderStatus::CashDue, OrderStatus::NeedsAttention, OrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['driver' => __('shop.error_driver_order')]);
        }
        $driver = DeliveryDriver::query()->where('is_active', true)->findOrFail($driverId);
        $delivery = OrderDelivery::query()->firstOrNew(['order_id' => $order->id]);
        if ($delivery->picked_up_at !== null) {
            throw ValidationException::withMessages(['driver' => __('shop.error_driver_picked_up')]);
        }
        $delivery->fill(['delivery_driver_id' => $driver->id, 'assigned_by' => $officeUserId, 'assigned_at' => now()])->save();

        return $delivery;
    }

    /**
     * A driver's own deliveries: still to make, then today's done.
     *
     * @return list<array<string, mixed>>
     */
    public function forDriver(int $userId): array
    {
        $driver = DeliveryDriver::query()->where('user_id', $userId)->first();
        if ($driver === null) {
            return [];
        }

        return OrderDelivery::query()->with(['order.items', 'order.vendor:id,name'])->where('delivery_driver_id', $driver->id)
            ->where(fn ($q) => $q->whereNull('delivered_at')->orWhere('delivered_at', '>=', now()->startOfDay()))
            ->orderByRaw('delivered_at is not null')->orderBy('assigned_at')->get()
            ->map(fn (OrderDelivery $d) => [
                'id' => $d->id,
                'number' => $d->order->number,
                'shop' => $d->order->vendor?->name,
                'recipient' => $d->order->address_snapshot['recipient_name'] ?? null,
                'phone' => $d->order->address_snapshot['phone'] ?? null,
                'address' => collect([$d->order->address_snapshot['street'] ?? null, $d->order->address_snapshot['island'] ?? null, $d->order->address_snapshot['atoll'] ?? null])->filter()->implode(', '),
                'items' => $d->order->items->map(fn ($i) => (int) $i->quantity.' × '.$i->title)->values()->all(),
                'cash_due' => $d->order->paid_at === null ? (string) $d->order->total : null,
                'picked_up_at' => $d->picked_up_at?->format('H:i'),
                'delivered_at' => $d->delivered_at?->format('H:i'),
                'proof_url' => $d->proof_media_file_id !== null ? route('deliveries.proof', $d->id) : null,
            ])->values()->all();
    }

    public function pickUp(int $userId, int $deliveryId): OrderDelivery
    {
        $delivery = $this->mine($userId, $deliveryId);
        if ($delivery->picked_up_at !== null) {
            return $delivery;
        }
        $order = $delivery->order;
        $scope = app(ResolveVendorScopeAction::class)->forOffice((int) $order->vendor_id, $userId);
        $fulfil = app(FulfilVendorOrderAction::class);
        // Akuru packed it: start it first. The shop packed it: it goes straight out.
        if ($order->status !== OrderStatus::Processing && AkuruFulfilment::takesStep($order, 'processing')) {
            $fulfil->advance($scope, $order->id, 'processing');
        }
        $fulfil->advance($scope, $order->id, 'dispatched', ['carrier' => __('shop.akuru_courier_carrier', ['name' => $delivery->driver->name]), 'tracking_note' => __('shop.out_for_delivery')]);
        $delivery->update(['picked_up_at' => now()]);

        return $delivery;
    }

    public function deliver(int $userId, int $deliveryId, UploadedFile $photo, ?string $note = null, bool $cashReceived = false): OrderDelivery
    {
        $delivery = $this->mine($userId, $deliveryId);
        if ($delivery->picked_up_at === null || $delivery->delivered_at !== null) {
            throw ValidationException::withMessages(['photo' => __('shop.error_driver_step')]);
        }
        $media = app(StorePrivateMediaAction::class)->execute($photo, $userId, self::PROOF_MIMES, 10 * 1024 * 1024);
        $order = $delivery->order;
        DB::transaction(function () use ($delivery, $order, $userId, $media, $note, $cashReceived) {
            $scope = app(ResolveVendorScopeAction::class)->forOffice((int) $order->vendor_id, $userId);
            app(FulfilVendorOrderAction::class)->advance($scope, $order->id, 'delivered', ['cash_received' => $cashReceived]);
            $delivery->update(['delivered_at' => now(), 'proof_media_file_id' => (int) $media['id'], 'note' => trim((string) $note) ?: null]);
            OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'delivered_by_driver', 'actor_user_id' => $userId, 'created_at' => now(), 'note' => $delivery->driver->name]);
        });

        return $delivery->refresh();
    }

    /** The photo — for the office, or the driver who took it. */
    public function proof(int $deliveryId): ?array
    {
        $delivery = OrderDelivery::query()->find($deliveryId);

        return $delivery?->proof_media_file_id !== null ? app(ReadPrivateMediaAction::class)->execute((int) $delivery->proof_media_file_id) : null;
    }

    public function driverOf(int $deliveryId): ?int
    {
        return OrderDelivery::query()->whereKey($deliveryId)->with('driver:id,user_id')->first()?->driver?->user_id;
    }

    /**
     * Per order on the office's page: who has it, and where it stands.
     *
     * @param  list<int>  $orderIds
     * @return array<int, array{driver_id: int, driver: string, picked_up_at: ?string, delivered_at: ?string, proof_url: ?string}>
     */
    public function forOrders(array $orderIds): array
    {
        return OrderDelivery::query()->with('driver:id,name')->whereIn('order_id', $orderIds)->get()
            ->mapWithKeys(fn (OrderDelivery $d) => [(int) $d->order_id => [
                'driver_id' => (int) $d->delivery_driver_id, 'driver' => (string) $d->driver?->name,
                'picked_up_at' => $d->picked_up_at?->format('Y-m-d H:i'), 'delivered_at' => $d->delivered_at?->format('Y-m-d H:i'),
                'proof_url' => $d->proof_media_file_id !== null ? route('deliveries.proof', $d->id) : null,
            ]])->all();
    }

    private function mine(int $userId, int $deliveryId): OrderDelivery
    {
        return OrderDelivery::query()->with(['order', 'driver'])->whereKey($deliveryId)
            ->whereHas('driver', fn ($q) => $q->where('user_id', $userId)->where('is_active', true))->firstOrFail();
    }
}
