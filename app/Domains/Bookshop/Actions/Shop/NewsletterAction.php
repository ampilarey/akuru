<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorNewsletterSubscriber;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A shop's newsletter sign-up (BOOKSHOP_PLAN §6.3 "Newsletter: collect
 * emails for the vendor's news, with consent", slice B9c). Anyone may sign
 * up on a shop's page with their address and explicit consent; signing up
 * again after leaving renews the consent. Each subscriber has a token for
 * a one-click-then-confirm unsubscribe page the shop links in its mailings.
 * Akuru sends nothing itself: the shop exports its list.
 */
class NewsletterAction
{
    public function subscribe(string $vendorSlug, string $email, ?string $name, bool $consent, ?int $userId): VendorNewsletterSubscriber
    {
        $vendor = Vendor::query()->where('slug', $vendorSlug)->where('status', VendorStatus::Active->value)->firstOrFail();
        if (! $consent) {
            throw ValidationException::withMessages(['consent' => __('shop.error_newsletter_consent')]);
        }
        $email = mb_strtolower(trim($email));
        $row = VendorNewsletterSubscriber::query()->firstOrNew(['vendor_id' => $vendor->id, 'email' => $email]);
        $row->fill([
            'name' => trim((string) $name) !== '' ? mb_substr(trim((string) $name), 0, 120) : $row->name,
            'user_id' => $userId ?? $row->user_id,
            'consented_at' => $row->exists && $row->unsubscribed_at === null ? $row->consented_at : now(),
            'unsubscribed_at' => null,
        ]);
        $row->token ??= Str::random(48);
        $row->save();

        return $row;
    }

    /**
     * @return array{email: string, shop: string, subscribed: bool}|null
     */
    public function find(string $token): ?array
    {
        $row = VendorNewsletterSubscriber::query()->where('token', $token)->with('vendor:id,name')->first();

        return $row === null ? null : ['email' => $row->email, 'shop' => (string) $row->vendor?->name, 'subscribed' => $row->unsubscribed_at === null];
    }

    public function unsubscribe(string $token): bool
    {
        return VendorNewsletterSubscriber::query()->where('token', $token)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now()]) > 0;
    }
}
