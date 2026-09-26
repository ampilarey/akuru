<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\ResolveShopHostAction;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\HostDns;
use Illuminate\Validation\ValidationException;

/**
 * The office's side of a shop's own domain (slice B9f): the requests with
 * what each domain points at now, turning one on once it points at Akuru
 * (and the domain is added in the hosting panel), or turning it off.
 */
class DecideVendorHostAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return Vendor::query()->whereNotNull('custom_host')->orderByRaw("custom_host_status = 'active'")->orderBy('name')->get()
            ->map(fn (Vendor $v) => [
                'vendor_id' => $v->id, 'vendor' => $v->name, 'slug' => $v->slug, 'host' => $v->custom_host, 'status' => $v->custom_host_status,
                'requested_at' => $v->custom_host_requested_at?->toDateTimeString(), 'approved_at' => $v->custom_host_approved_at?->toDateTimeString(),
            ])->values()->all();
    }

    /**
     * @return array{host: string, addresses: list<string>, aliases: list<string>, ours: list<string>, points_here: bool}
     */
    public function check(int $vendorId): array
    {
        $vendor = Vendor::query()->whereNotNull('custom_host')->findOrFail($vendorId);
        $dns = app(HostDns::class);
        $found = $dns->lookup((string) $vendor->custom_host);
        $canonical = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $ours = $dns->lookup($canonical)['addresses'];

        return [
            'host' => (string) $vendor->custom_host,
            'addresses' => $found['addresses'],
            'aliases' => $found['aliases'],
            'ours' => $ours,
            'points_here' => array_intersect($found['addresses'], $ours) !== [] || in_array($canonical, $found['aliases'], true),
        ];
    }

    public function approve(int $vendorId): Vendor
    {
        $vendor = Vendor::query()->whereNotNull('custom_host')->findOrFail($vendorId);
        if ($vendor->custom_host_status !== 'requested') {
            throw ValidationException::withMessages(['host' => __('shop.error_host_not_requested')]);
        }
        $vendor->update(['custom_host_status' => 'active', 'custom_host_approved_at' => now()]);
        ResolveShopHostAction::forget($vendor->custom_host);
        app(NotifyBookshopUserAction::class)->vendor((int) $vendor->id, __('shop.notice_host_active_title'), __('shop.notice_host_active_body', ['host' => $vendor->custom_host]), '/vendor');

        return $vendor->refresh();
    }

    public function turnOff(int $vendorId): Vendor
    {
        $vendor = Vendor::query()->whereNotNull('custom_host')->findOrFail($vendorId);
        $vendor->update(['custom_host_status' => 'requested', 'custom_host_approved_at' => null]);
        ResolveShopHostAction::forget($vendor->custom_host);

        return $vendor->refresh();
    }
}
