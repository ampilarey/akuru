<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Models\VendorStorefrontVersion;
use App\Domains\Bookshop\Support\Theme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The draft goes live (BOOKSHOP_PLAN §6.2 "save as draft, preview, publish,
 * roll back"). Publishing re-checks every colour pair — a theme that fails
 * to read is refused with the reasons — and appends a numbered, optionally
 * named version, so any earlier look can come back. Rolling back publishes
 * an earlier version as a new one (the trail is append-only) and makes it
 * the draft too. Owners only: the page is the shop's face.
 */
class PublishStorefrontAction
{
    public function publish(VendorScope $scope, ?string $note = null): VendorStorefront
    {
        $this->owner($scope);

        return DB::transaction(function () use ($scope, $note) {
            $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->lockForUpdate()->firstOrFail();
            $vendor = Vendor::query()->findOrFail($scope->vendorId);
            $theme = Theme::normalize((array) ($storefront->draft_theme ?? []), $vendor);
            $problems = Theme::problems($theme);
            if ($problems !== []) {
                throw ValidationException::withMessages(['theme' => __('shop.error_theme_unreadable', ['count' => count($problems)])]);
            }
            $preset = $theme['preset'] !== null ? Theme::preset($theme['preset']) : [];
            if (($preset['badge'] ?? null) !== null && ! $vendor->hasBadge($preset['badge'])) {
                throw ValidationException::withMessages(['theme' => __('shop.error_preset_locked')]);
            }

            return $this->record($storefront, (array) $storefront->draft_identity, $theme, $scope->userId, trim((string) $note) ?: null);
        });
    }

    public function rollBack(VendorScope $scope, int $versionId): VendorStorefront
    {
        $this->owner($scope);

        return DB::transaction(function () use ($scope, $versionId) {
            $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->lockForUpdate()->firstOrFail();
            $version = VendorStorefrontVersion::query()->where('vendor_storefront_id', $storefront->id)->whereKey($versionId)->firstOrFail();
            $storefront->update(['draft_identity' => $version->identity, 'draft_theme' => $version->theme]);

            return $this->record($storefront, (array) $version->identity, (array) $version->theme, $scope->userId, __('shop.rolled_back_note', ['number' => $version->number]));
        });
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $theme
     */
    private function record(VendorStorefront $storefront, array $identity, array $theme, int $userId, ?string $note): VendorStorefront
    {
        $number = (int) VendorStorefrontVersion::query()->where('vendor_storefront_id', $storefront->id)->max('number') + 1;
        $version = VendorStorefrontVersion::query()->create([
            'vendor_storefront_id' => $storefront->id,
            'number' => $number,
            'identity' => $identity,
            'theme' => $theme,
            'note' => $note,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
        $storefront->update([
            'published_identity' => $identity,
            'published_theme' => $theme,
            'published_version_id' => $version->id,
            'published_at' => now(),
            'published_by' => $userId,
        ]);

        return $storefront->refresh();
    }

    private function owner(VendorScope $scope): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
    }
}
