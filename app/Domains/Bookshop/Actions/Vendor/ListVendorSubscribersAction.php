<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorNewsletterSubscriber;

/**
 * The shop's newsletter list (BOOKSHOP_PLAN §6.3, slice B9c): who signed
 * up and when, for the shop's own mailings — with each person's
 * unsubscribe link, which the shop must include. Only this shop's list.
 */
class ListVendorSubscribersAction
{
    /**
     * @return array{active: int, left: int, recent: list<array<string, mixed>>}
     */
    public function summary(VendorScope $scope): array
    {
        $base = VendorNewsletterSubscriber::query()->where('vendor_id', $scope->vendorId);

        return [
            'active' => (clone $base)->whereNull('unsubscribed_at')->count(),
            'left' => (clone $base)->whereNotNull('unsubscribed_at')->count(),
            'recent' => (clone $base)->whereNull('unsubscribed_at')->orderByDesc('consented_at')->limit(10)->get()
                ->map(fn (VendorNewsletterSubscriber $s) => ['email' => $s->email, 'name' => $s->name, 'since' => $s->consented_at?->toDateString()])->values()->all(),
        ];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function all(VendorScope $scope): iterable
    {
        $rows = VendorNewsletterSubscriber::query()->where('vendor_id', $scope->vendorId)->whereNull('unsubscribed_at')->orderBy('consented_at')->cursor();
        foreach ($rows as $s) {
            yield ['email' => $s->email, 'name' => $s->name, 'consented_at' => $s->consented_at?->toDateTimeString(), 'unsubscribe_url' => route('public.shop.newsletter.unsubscribe', $s->token)];
        }
    }
}
