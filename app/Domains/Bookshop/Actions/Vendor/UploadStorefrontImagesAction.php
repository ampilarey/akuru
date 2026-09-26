<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorStorefrontImage;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The shop's image library (BOOKSHOP_PLAN §6.3 "image gallery", §10):
 * public media the sections pick from — a hero slide, a story photo, a
 * gallery, a share image. Uploaded once, used anywhere on the storefront;
 * `SectionTypes` accepts only ids from this library, so a section can
 * never point at another shop's picture or at a private file.
 */
class UploadStorefrontImagesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(VendorScope $scope): array
    {
        $images = app(ResolvePublicImageVariantAction::class);

        return VendorStorefrontImage::query()->where('vendor_id', $scope->vendorId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (VendorStorefrontImage $i) => [
                'id' => $i->id,
                'media_file_id' => (int) $i->media_file_id,
                'alt' => $i->alt,
                'url' => $images->execute((int) $i->media_file_id, ResolveStorefrontAction::LOGO_WIDTH),
            ])->values()->all();
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<int> the library ids made
     */
    public function upload(VendorScope $scope, array $files, ?string $alt = null): array
    {
        $max = (int) config('bookshop.storefront.max_library_images', 60);
        $have = VendorStorefrontImage::query()->where('vendor_id', $scope->vendorId)->count();
        if ($have + count($files) > $max) {
            throw ValidationException::withMessages(['images' => __('shop.error_too_many_images', ['max' => $max])]);
        }
        $ids = [];
        $order = (int) VendorStorefrontImage::query()->where('vendor_id', $scope->vendorId)->max('sort_order');
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $stored = app(StorePublicMediaAction::class)->execute(
                $file,
                $scope->userId,
                (array) config('bookshop.storefront.images.mimes'),
                ['vendor_id' => $scope->vendorId, 'library' => true],
                'storefronts',
            );
            $ids[] = VendorStorefrontImage::query()->create([
                'vendor_id' => $scope->vendorId,
                'media_file_id' => (int) $stored['id'],
                'alt' => is_string($alt) && trim($alt) !== '' ? mb_substr(trim($alt), 0, 200) : null,
                'sort_order' => ++$order,
            ])->id;
        }

        return $ids;
    }

    public function describe(VendorScope $scope, int $imageId, ?string $alt): void
    {
        VendorStorefrontImage::query()->where('vendor_id', $scope->vendorId)->whereKey($imageId)->firstOrFail()
            ->update(['alt' => is_string($alt) && trim($alt) !== '' ? mb_substr(trim($alt), 0, 200) : null]);
    }

    /** Leaves the library; a section still pointing at it shows nothing there. */
    public function remove(VendorScope $scope, int $imageId): void
    {
        VendorStorefrontImage::query()->where('vendor_id', $scope->vendorId)->whereKey($imageId)->firstOrFail()->delete();
    }
}
