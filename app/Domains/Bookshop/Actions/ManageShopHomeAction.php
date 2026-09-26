<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ShopHomeFeature;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The office merchandises the shop home (BOOKSHOP_PLAN §7 "featured on the
 * shop home; collections on the shop home; the shop home's hero and
 * featured strips"): hero slides with a photo and a link to a shop, a
 * category, a product or a collection; hand-picked featured products (any
 * shop's, shop-wide only); featured collections. In the office's order;
 * each may be switched off. The public page shows only what is for sale.
 */
class ManageShopHomeAction
{
    public const LINK_KINDS = ['vendor', 'category', 'product', 'collection'];

    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        $images = app(ResolvePublicImageVariantAction::class);
        $features = ShopHomeFeature::query()->with(['product:id,title,slug,vendor_id', 'product.vendor:id,name', 'collection:id,name,vendor_id', 'collection.vendor:id,name'])
            ->orderBy('kind')->orderBy('sort_order')->orderBy('id')->get();

        return [
            'features' => $features->map(fn (ShopHomeFeature $f) => [
                'id' => $f->id,
                'kind' => $f->kind,
                'is_active' => (bool) $f->is_active,
                'heading' => $f->heading, 'heading_dv' => $f->heading_dv, 'heading_ar' => $f->heading_ar,
                'subheading' => $f->subheading, 'subheading_dv' => $f->subheading_dv, 'subheading_ar' => $f->subheading_ar,
                'image' => $f->media_file_id !== null ? $images->execute((int) $f->media_file_id, 480) : null,
                'link' => $f->link,
                'label' => match ($f->kind) {
                    'product' => $f->product !== null ? $f->product->title.' · '.$f->product->vendor?->name : '—',
                    'collection' => $f->collection !== null ? $f->collection->name.' · '.$f->collection->vendor?->name : '—',
                    default => $f->heading ?? '—',
                },
            ])->values()->all(),
            'options' => [
                'products' => ListShopProductsAction::forSale()->where('visibility', ProductVisibility::Shop->value)->with('vendor:id,name')->orderBy('title')->limit(1000)->get(['id', 'title', 'slug', 'vendor_id'])
                    ->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'label' => $p->title.' · '.$p->vendor?->name])->values()->all(),
                'collections' => VendorCollection::query()->where('is_active', true)->whereHas('vendor', fn ($v) => $v->where('status', VendorStatus::Active->value))->with('vendor:id,name')->orderBy('name')->get(['id', 'name', 'vendor_id'])
                    ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name.' · '.$c->vendor?->name])->values()->all(),
                'vendors' => Vendor::query()->where('status', VendorStatus::Active->value)->orderBy('name')->get(['slug', 'name'])->map(fn ($v) => ['slug' => $v->slug, 'label' => $v->name])->values()->all(),
                'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'label' => $c->name])->values()->all(),
                'link_kinds' => self::LINK_KINDS,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?int $featureId, int $userId, ?UploadedFile $image = null): ShopHomeFeature
    {
        $feature = $featureId === null ? new ShopHomeFeature : ShopHomeFeature::query()->findOrFail($featureId);
        $kind = $feature->exists ? $feature->kind : (string) ($data['kind'] ?? '');
        if (! in_array($kind, ShopHomeFeature::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('shop.error_home_kind')]);
        }
        if (! $feature->exists) {
            $max = (int) match ($kind) {
                'hero' => config('bookshop.merchandising.home_hero_max', 5),
                'product' => config('bookshop.merchandising.home_featured_max', 12),
                default => config('bookshop.merchandising.home_collections_max', 6),
            };
            if (ShopHomeFeature::query()->where('kind', $kind)->count() >= $max) {
                throw ValidationException::withMessages(['kind' => __('shop.error_home_full', ['max' => $max])]);
            }
            $feature->kind = $kind;
            $feature->created_by = $userId;
            $feature->sort_order = (int) ShopHomeFeature::query()->where('kind', $kind)->max('sort_order') + 1;
        }
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
        $feature->is_active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        if ($kind === 'product') {
            $productId = (int) ($data['product_id'] ?? $feature->product_id);
            if (! ListShopProductsAction::forSale()->where('visibility', ProductVisibility::Shop->value)->whereKey($productId)->exists()) {
                throw ValidationException::withMessages(['product_id' => __('shop.error_home_product')]);
            }
            $feature->product_id = $productId;
        } elseif ($kind === 'collection') {
            $collectionId = (int) ($data['vendor_collection_id'] ?? $feature->vendor_collection_id);
            if (! VendorCollection::query()->whereKey($collectionId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['vendor_collection_id' => __('shop.error_home_collection')]);
            }
            $feature->vendor_collection_id = $collectionId;
            $feature->heading = $text($data['heading'] ?? null, 160);
            $feature->heading_dv = $text($data['heading_dv'] ?? null, 160);
            $feature->heading_ar = $text($data['heading_ar'] ?? null, 160);
        } else {
            foreach (['heading' => 160, 'subheading' => 300] as $field => $max) {
                foreach (['', '_dv', '_ar'] as $suffix) {
                    $feature->{$field.$suffix} = $text($data[$field.$suffix] ?? null, $max);
                }
            }
            if ($feature->heading === null) {
                throw ValidationException::withMessages(['heading' => __('shop.error_home_heading')]);
            }
            $feature->link = $this->link((array) ($data['link'] ?? []));
            if ($image !== null) {
                $stored = app(StorePublicMediaAction::class)->execute($image, $userId, (array) config('bookshop.storefront.images.mimes'), ['shop_home' => true], 'shop-home');
                $feature->media_file_id = (int) $stored['id'];
            }
        }
        $feature->save();

        return $feature->refresh();
    }

    public function remove(int $featureId): void
    {
        ShopHomeFeature::query()->findOrFail($featureId)->delete();
    }

    /** Up or down among its own kind. */
    public function move(int $featureId, int $direction): void
    {
        $feature = ShopHomeFeature::query()->findOrFail($featureId);
        $siblings = ShopHomeFeature::query()->where('kind', $feature->kind)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (ShopHomeFeature $f) => $f->id === $feature->id);
        $swap = $siblings->get($index + ($direction < 0 ? -1 : 1));
        if ($swap === null) {
            return;
        }
        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$index + ($direction < 0 ? -1 : 1)]] = [$swap, $feature];
        foreach (array_values($ordered) as $i => $f) {
            ShopHomeFeature::query()->whereKey($f->id)->update(['sort_order' => $i + 1]);
        }
    }

    /**
     * A hero button's target inside the shop, never a bare URL.
     *
     * @param  array<string, mixed>  $raw
     * @return array{kind: string, target: string}|null
     */
    private function link(array $raw): ?array
    {
        $kind = (string) ($raw['kind'] ?? '');
        $target = trim((string) ($raw['target'] ?? ''));
        if ($kind === '' || $target === '') {
            return null;
        }
        $ok = match ($kind) {
            'vendor' => Vendor::query()->where('slug', $target)->where('status', VendorStatus::Active->value)->exists(),
            'category' => ProductCategory::query()->where('slug', $target)->where('is_active', true)->exists(),
            'product' => ListShopProductsAction::forSale()->where('slug', $target)->exists(),
            'collection' => VendorCollection::query()->whereKey((int) $target)->where('is_active', true)->exists(),
            default => false,
        };
        if (! $ok) {
            throw ValidationException::withMessages(['link' => __('shop.error_home_link')]);
        }

        return ['kind' => $kind, 'target' => $target];
    }
}
