<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\CustomCss;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * A shop's own CSS, its side (slice B10c, ADR-039): cleaned and confined
 * (`Support/CustomCss`), then **sent to the office** — the preview shows it
 * at once, visitors only once the office approves. Sending empty CSS, or
 * removing it, takes the shop's CSS off its page straight away (making the
 * page plainer never needs approval). Owners only: it changes the shop's
 * public face.
 */
class SubmitStorefrontCssAction
{
    /**
     * @return array{live: ?string, pending: ?string, status: ?string, note: ?string, submitted_at: ?string, max_bytes: int}
     */
    public function get(VendorScope $scope): array
    {
        $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->first();

        return [
            'live' => $storefront?->custom_css,
            'pending' => $storefront?->custom_css_pending,
            'status' => $storefront?->custom_css_status,
            'note' => $storefront?->custom_css_note,
            'submitted_at' => $storefront?->custom_css_submitted_at?->toDateTimeString(),
            'max_bytes' => CustomCss::MAX_BYTES,
        ];
    }

    public function submit(VendorScope $scope, ?string $css): VendorStorefront
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        $result = CustomCss::clean((string) $css);
        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['css' => array_map(fn (string $reason) => __('shop.css_error_'.$reason), $result['errors'])]);
        }
        $storefront = VendorStorefront::query()->firstOrCreate(['vendor_id' => $scope->vendorId]);
        if ($result['css'] === '') {
            return $this->remove($scope);
        }
        if ($result['css'] === $storefront->custom_css) {
            $storefront->update(['custom_css_pending' => null, 'custom_css_status' => 'approved']);

            return $storefront->refresh();
        }
        $storefront->update([
            'custom_css_pending' => $result['css'], 'custom_css_status' => 'pending', 'custom_css_note' => null,
            'custom_css_submitted_at' => now(), 'custom_css_reviewed_at' => null, 'custom_css_reviewed_by' => null,
        ]);
        app(NotifyBookshopUserAction::class)->office(__('shop.notice_css_submitted_title'), __('shop.notice_css_submitted_body', ['vendor' => $scope->vendorName]), '/admin/bookshop');

        return $storefront->refresh();
    }

    public function remove(VendorScope $scope): VendorStorefront
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        $storefront = VendorStorefront::query()->firstOrCreate(['vendor_id' => $scope->vendorId]);
        $storefront->update(['custom_css' => null, 'custom_css_pending' => null, 'custom_css_status' => null, 'custom_css_note' => null]);
        app(ResolveStorefrontAction::class)->forget($scope->vendorId);

        return $storefront->refresh();
    }
}
