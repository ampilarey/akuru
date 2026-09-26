<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\StorefrontTheme;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\Theme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The theme gallery, a shop's side (slice B10d, ADR-039): the published
 * looks; applying one to the draft (the colours, fonts, scale and shape at
 * once, and its CSS — approved with the theme — going live when the shop
 * publishes); and offering the shop's own published look to the gallery,
 * for the office to publish. Owners only for applying and offering.
 */
class StorefrontGalleryAction
{
    /**
     * @return array{themes: list<array<string, mixed>>, offered: ?array<string, mixed>}
     */
    public function list(VendorScope $scope): array
    {
        $offered = StorefrontTheme::query()->where('source_vendor_id', $scope->vendorId)->whereIn('status', ['submitted', 'declined'])->latest('id')->first();

        return [
            'themes' => StorefrontTheme::query()->where('status', 'published')->with('sourceVendor:id,name')->orderByDesc('uses_count')->orderBy('name')->get()
                ->map(fn (StorefrontTheme $t) => $t->present())->values()->all(),
            'offered' => $offered?->present(),
        ];
    }

    public function apply(VendorScope $scope, int $themeId): VendorStorefront
    {
        $this->owner($scope);
        $theme = StorefrontTheme::query()->where('status', 'published')->findOrFail($themeId);
        $vendor = Vendor::query()->findOrFail($scope->vendorId);

        return DB::transaction(function () use ($scope, $theme, $vendor) {
            $storefront = VendorStorefront::query()->firstOrCreate(['vendor_id' => $scope->vendorId]);
            $normalized = Theme::normalize((array) $theme->theme, $vendor);
            $updates = ['draft_theme' => $normalized];
            if ($theme->custom_css !== null && $theme->custom_css !== '') {
                // Approved with the theme: it goes live with the next publish (the preview shows it now).
                $updates += ['custom_css_pending' => $theme->custom_css, 'custom_css_status' => 'theme', 'custom_css_note' => null, 'custom_css_submitted_at' => now()];
            } elseif (in_array($storefront->custom_css_status, ['theme', 'pending', 'declined'], true)) {
                // An earlier theme's (or an unapproved) CSS would fight the new look: it goes; the live CSS stays until publish replaces the look.
                $updates += ['custom_css_pending' => null, 'custom_css_status' => $storefront->custom_css !== null ? 'approved' : null, 'custom_css_note' => null];
            }
            $storefront->update($updates);
            $theme->increment('uses_count');

            return $storefront->refresh();
        });
    }

    public function offer(VendorScope $scope, string $name, ?string $description): StorefrontTheme
    {
        $this->owner($scope);
        $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->first();
        if ($storefront === null || ! $storefront->isPublished()) {
            throw ValidationException::withMessages(['name' => __('shop.error_gallery_publish_first')]);
        }
        if (StorefrontTheme::query()->where('source_vendor_id', $scope->vendorId)->where('status', 'submitted')->exists()) {
            throw ValidationException::withMessages(['name' => __('shop.error_gallery_one_waiting')]);
        }
        $theme = StorefrontTheme::query()->create([
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => trim($name),
            'description' => trim((string) $description) ?: null,
            'theme' => (array) $storefront->published_theme,
            'custom_css' => $storefront->custom_css,
            'status' => 'submitted',
            'source' => 'shop',
            'source_vendor_id' => $scope->vendorId,
            'submitted_by' => $scope->userId,
        ]);
        app(NotifyBookshopUserAction::class)->office(__('shop.notice_theme_offered_title'), __('shop.notice_theme_offered_body', ['vendor' => $scope->vendorName, 'name' => $theme->name]), '/admin/bookshop');

        return $theme;
    }

    private function owner(VendorScope $scope): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
    }
}
