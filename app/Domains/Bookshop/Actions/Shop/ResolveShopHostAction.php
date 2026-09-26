<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Support\Facades\Cache;

/**
 * Where a request to one of the Bookstore's other addresses goes (slice
 * B9f): the whole-shop subdomain to the same path under /shop, and a
 * shop's own domain — only once the office turned it on, and only while
 * the shop is active — to that shop's page (its root to /shop/<slug>, a
 * path to the same path under it). Everything else: null, the request
 * carries on. The one canonical site is `app.url`.
 */
class ResolveShopHostAction
{
    public function redirectFor(string $host, string $path, ?string $query): ?string
    {
        $host = strtolower($host);
        $canonical = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($host === '' || $host === $canonical) {
            return null;
        }
        $path = '/'.ltrim($path, '/');
        $suffix = $path === '/' ? '' : rtrim($path, '/');

        $shopHost = strtolower(trim((string) config('bookshop.hosts.shop_host')));
        if ($shopHost !== '' && $host === $shopHost) {
            return $this->url('/shop'.$suffix, $query);
        }

        $slug = $this->vendorSlug($host);

        return $slug === null ? null : $this->url('/shop/'.$slug.$suffix, $query);
    }

    public function vendorSlug(string $host): ?string
    {
        $slug = Cache::remember('bookshop_host:'.$host, (int) config('bookshop.hosts.cache_seconds', 300), fn () => (string) Vendor::query()
            ->where('custom_host', $host)->where('custom_host_status', 'active')->where('status', VendorStatus::Active->value)->value('slug'));

        return $slug === '' ? null : $slug;
    }

    public static function forget(?string $host): void
    {
        if ($host !== null && $host !== '') {
            Cache::forget('bookshop_host:'.strtolower($host));
        }
    }

    private function url(string $path, ?string $query): string
    {
        return rtrim((string) config('app.url'), '/').$path.($query !== null && $query !== '' ? '?'.$query : '');
    }
}
