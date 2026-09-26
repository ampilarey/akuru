<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\Models\VendorStorefront;
use Illuminate\Validation\ValidationException;

/**
 * The office's side of a shop's own CSS (slice B10c, ADR-039): what is
 * waiting, with what is live beside it and the shop's preview one click
 * away; approve (it goes live) or send it back with a note; and take a
 * shop's live CSS down at any time.
 */
class DecideStorefrontCssAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return VendorStorefront::query()->with('vendor:id,name,slug')
            ->where(fn ($q) => $q->where('custom_css_status', 'pending')->orWhereNotNull('custom_css'))
            ->orderByRaw("custom_css_status = 'pending' desc")->orderByDesc('custom_css_submitted_at')->get()
            ->map(fn (VendorStorefront $s) => [
                'vendor_id' => $s->vendor_id, 'vendor' => $s->vendor?->name, 'slug' => $s->vendor?->slug,
                'status' => $s->custom_css_status, 'pending' => $s->custom_css_pending, 'live' => $s->custom_css,
                'submitted_at' => $s->custom_css_submitted_at?->toDateTimeString(),
                'preview_url' => route('admin.bookshop.storefront.preview', $s->vendor_id),
            ])->values()->all();
    }

    public function approve(int $vendorId, int $byUserId): VendorStorefront
    {
        $storefront = $this->pending($vendorId);
        $storefront->update([
            'custom_css' => $storefront->custom_css_pending, 'custom_css_pending' => null, 'custom_css_status' => 'approved',
            'custom_css_note' => null, 'custom_css_reviewed_at' => now(), 'custom_css_reviewed_by' => $byUserId,
        ]);
        app(ResolveStorefrontAction::class)->forget($vendorId);
        app(NotifyBookshopUserAction::class)->vendor($vendorId, __('shop.notice_css_approved_title'), __('shop.notice_css_approved_body'), '/vendor/storefront');

        return $storefront->refresh();
    }

    public function decline(int $vendorId, int $byUserId, string $note): VendorStorefront
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('shop.error_css_decline_note')]);
        }
        $storefront = $this->pending($vendorId);
        $storefront->update(['custom_css_status' => 'declined', 'custom_css_note' => trim($note), 'custom_css_reviewed_at' => now(), 'custom_css_reviewed_by' => $byUserId]);
        app(NotifyBookshopUserAction::class)->vendor($vendorId, __('shop.notice_css_declined_title'), __('shop.notice_css_declined_body', ['note' => trim($note)]), '/vendor/storefront');

        return $storefront->refresh();
    }

    /** Take the shop's live CSS off its page now (the pending one, if any, stays for review). */
    public function takeDown(int $vendorId, int $byUserId, string $note): VendorStorefront
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('shop.error_css_decline_note')]);
        }
        $storefront = VendorStorefront::query()->where('vendor_id', $vendorId)->whereNotNull('custom_css')->firstOrFail();
        $storefront->update(['custom_css' => null, 'custom_css_note' => trim($note), 'custom_css_reviewed_at' => now(), 'custom_css_reviewed_by' => $byUserId,
            'custom_css_status' => $storefront->custom_css_status === 'pending' ? 'pending' : 'taken_down']);
        app(ResolveStorefrontAction::class)->forget($vendorId);
        app(NotifyBookshopUserAction::class)->vendor($vendorId, __('shop.notice_css_taken_down_title'), __('shop.notice_css_declined_body', ['note' => trim($note)]), '/vendor/storefront');

        return $storefront->refresh();
    }

    private function pending(int $vendorId): VendorStorefront
    {
        $storefront = VendorStorefront::query()->where('vendor_id', $vendorId)->first();
        if ($storefront === null || $storefront->custom_css_status !== 'pending' || $storefront->custom_css_pending === null) {
            throw ValidationException::withMessages(['css' => __('shop.error_css_nothing_pending')]);
        }

        return $storefront;
    }
}
