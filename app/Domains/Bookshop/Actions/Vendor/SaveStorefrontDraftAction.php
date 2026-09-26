<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\Identity;
use App\Domains\Bookshop\Support\Theme;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The vendor saves a draft of the storefront's identity and theme
 * (BOOKSHOP_PLAN §6.1, §6.2). A draft may be saved with colour pairs that
 * fail to read — the designer shows the reasons — but a locked preset
 * (Akuru's, without the partner badge) is refused outright. Publishing
 * (`PublishStorefrontAction`) is where readability is enforced.
 *
 * Owners and staff alike: designing the page is listing work.
 */
class SaveStorefrontDraftAction
{
    /**
     * @param  array<string, mixed>  $data  identity fields, `theme`, and `remove_images` (list of slots)
     * @param  array<string, UploadedFile>  $images  slot => file (logo, logo_dark, banner)
     * @return array{storefront: VendorStorefront, problems: list<array<string, mixed>>}
     */
    public function execute(VendorScope $scope, array $data, array $images = []): array
    {
        $vendor = Vendor::query()->findOrFail($scope->vendorId);
        $theme = Theme::normalize((array) ($data['theme'] ?? []), $vendor);
        $preset = $theme['preset'] !== null ? Theme::preset($theme['preset']) : [];
        if (($preset['badge'] ?? null) !== null && ! $vendor->hasBadge($preset['badge'])) {
            throw ValidationException::withMessages(['theme' => __('shop.error_preset_locked')]);
        }

        $storefront = DB::transaction(function () use ($scope, $data, $images, $theme) {
            $storefront = VendorStorefront::query()->firstOrCreate(['vendor_id' => $scope->vendorId]);
            $identity = Identity::normalize($data, $storefront->draft_identity);

            foreach ((array) ($data['remove_images'] ?? []) as $slot) {
                if (in_array($slot, Identity::IMAGES, true)) {
                    $identity['images'][$slot] = null;
                }
            }
            foreach ($images as $slot => $file) {
                if (! in_array($slot, Identity::IMAGES, true) || ! $file instanceof UploadedFile) {
                    continue;
                }
                $stored = app(StorePublicMediaAction::class)->execute(
                    $file,
                    $scope->userId,
                    (array) config('bookshop.storefront.images.mimes'),
                    ['vendor_id' => $scope->vendorId, 'slot' => $slot],
                    'storefronts',
                );
                $identity['images'][$slot] = (int) $stored['id'];
            }

            $storefront->update(['draft_identity' => $identity, 'draft_theme' => $theme]);

            return $storefront->refresh();
        });

        return ['storefront' => $storefront, 'problems' => Theme::problems($theme)];
    }
}
