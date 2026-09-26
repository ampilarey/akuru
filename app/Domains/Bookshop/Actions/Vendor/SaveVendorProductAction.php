<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A vendor creates or edits one of its own products (BOOKSHOP_PLAN §5).
 *
 * Scoped by construction: an edit finds the product *within the scope's
 * vendor* or 404s, and a new product is always the scope's. Photos are
 * public media (the shop shows them to everyone); descriptions go through
 * the sanitiser, so a vendor's text can carry emphasis and lists but never
 * script or style. SKUs are unique within a vendor. The slug is fixed at
 * creation — it is the product's address once B1b publishes it.
 */
class SaveVendorProductAction
{
    private const DETAIL_KEYS = ['author', 'publisher', 'year', 'pages', 'language', 'age_range', 'grade', 'subject'];

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $photos
     */
    public function execute(VendorScope $scope, array $data, ?int $productId = null, array $photos = []): Product
    {
        return DB::transaction(function () use ($scope, $data, $productId, $photos) {
            $product = $productId === null
                ? new Product(['vendor_id' => $scope->vendorId, 'created_by' => $scope->userId])
                : Product::query()->where('vendor_id', $scope->vendorId)->lockForUpdate()->findOrFail($productId);

            $this->guardSku($scope, $data['sku'] ?? null, $product->id);
            $this->guardPrices($data);

            $product->fill($this->columns($data));
            $product->vendor_id = $scope->vendorId;
            $product->updated_by = $scope->userId;
            if (! $product->exists) {
                $product->slug = $this->uniqueSlug((string) $data['title'], $scope->vendorSlug);
            }
            $product->save();

            if (array_key_exists('variants', $data) || ! empty($data['variants_sent'])) {
                $this->syncVariants($product, (array) ($data['variants'] ?? []));
            }
            $this->storePhotos($scope, $product, $photos);

            return $product->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $details = [];
        foreach (self::DETAIL_KEYS as $key) {
            $value = trim((string) ($data['details'][$key] ?? ''));
            if ($value !== '') {
                $details[$key] = $value;
            }
        }

        $tags = collect((array) ($data['tags'] ?? []))
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();

        return [
            'title' => $data['title'],
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'summary' => $data['summary'] ?? null,
            'summary_dv' => $data['summary_dv'] ?? null,
            'summary_ar' => $data['summary_ar'] ?? null,
            'description' => $this->richText($data['description'] ?? null),
            'description_dv' => $this->richText($data['description_dv'] ?? null),
            'description_ar' => $this->richText($data['description_ar'] ?? null),
            'product_category_id' => $data['product_category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost' => $data['cost'] ?? null,
            'currency' => (string) config('bookshop.currency', 'MVR'),
            'tax_class' => $data['tax_class'] ?? 'standard',
            'sku' => ($data['sku'] ?? null) ?: null,
            'barcode' => ($data['barcode'] ?? null) ?: null,
            'weight_grams' => $data['weight_grams'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'track_stock' => (bool) ($data['track_stock'] ?? true),
            'stock' => (int) ($data['stock'] ?? 0),
            'low_stock_at' => $data['low_stock_at'] ?? null,
            'lead_days' => $data['lead_days'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'visibility' => $data['visibility'] ?? 'shop',
            'tags' => $tags,
            'details' => $details,
        ];
    }

    /**
     * Text without block markup becomes paragraphs (a blank line starts a
     * new one) — including text with a stray inline tag, which a vendor
     * typing into a textarea will produce. Either way the result is
     * sanitised to the prose profile, so no script or style survives.
     */
    private function richText(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (! preg_match('/<(p|ul|ol|li|h[1-6]|br|div|blockquote)\b/i', $text)) {
            $paragraphs = preg_split('/\R{2,}/', $text) ?: [];
            $text = implode('', array_map(
                fn (string $p) => '<p>'.nl2br(str_contains($p, '<') ? trim($p) : e(trim($p)), false).'</p>',
                array_filter($paragraphs, fn (string $p) => trim($p) !== ''),
            ));
        }

        return app(HtmlSanitizer::class)->clean($text, HtmlSanitizer::PROFILE_LESSON);
    }

    private function guardSku(VendorScope $scope, mixed $sku, ?int $ignoreId): void
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return;
        }
        $taken = Product::query()
            ->where('vendor_id', $scope->vendorId)
            ->where('sku', $sku)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['sku' => __('shop.error_sku_taken')]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardPrices(array $data): void
    {
        $compare = $data['compare_at_price'] ?? null;
        if ($compare !== null && $compare !== '' && (float) $compare <= (float) $data['price']) {
            throw ValidationException::withMessages(['compare_at_price' => __('shop.error_compare_at')]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncVariants(Product $product, array $rows): void
    {
        $max = (int) config('bookshop.variants.max_per_product', 30);
        $rows = array_values(array_filter($rows, fn ($row) => trim((string) ($row['name'] ?? '')) !== ''));
        if (count($rows) > $max) {
            throw ValidationException::withMessages(['variants' => __('shop.error_too_many_variants', ['max' => $max])]);
        }

        $kept = [];
        foreach ($rows as $index => $row) {
            $variant = isset($row['id'])
                ? ProductVariant::query()->where('product_id', $product->id)->find((int) $row['id'])
                : null;
            $variant ??= new ProductVariant(['product_id' => $product->id]);
            $variant->fill([
                'name' => trim((string) $row['name']),
                'sku' => ($row['sku'] ?? null) ?: null,
                'price' => ($row['price'] ?? null) === '' ? null : ($row['price'] ?? null),
                'stock' => (int) ($row['stock'] ?? 0),
                'sort_order' => $index,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
            $variant->product_id = $product->id;
            $variant->save();
            $kept[] = $variant->id;
        }

        ProductVariant::query()->where('product_id', $product->id)->whereNotIn('id', $kept)->delete();
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    private function storePhotos(VendorScope $scope, Product $product, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        $max = (int) config('bookshop.photos.max_per_product', 8);
        $existing = ProductImage::query()->where('product_id', $product->id)->count();
        if ($existing + count($photos) > $max) {
            throw ValidationException::withMessages(['photos' => __('shop.error_too_many_photos', ['max' => $max, 'count' => $existing])]);
        }

        $next = (int) ProductImage::query()->where('product_id', $product->id)->max('sort_order') + ($existing > 0 ? 1 : 0);
        foreach ($photos as $photo) {
            $stored = app(StorePublicMediaAction::class)->execute(
                $photo,
                $scope->userId,
                (array) config('bookshop.photos.mimes'),
                ['product_id' => $product->id, 'vendor_id' => $scope->vendorId],
                'shop-products',
            );
            ProductImage::query()->create([
                'product_id' => $product->id,
                'media_file_id' => $stored['id'],
                'alt_text' => $product->title,
                'sort_order' => $next++,
            ]);
        }
    }

    private function uniqueSlug(string $title, string $vendorSlug): string
    {
        $base = Str::limit(Str::slug($title) ?: 'product', 90, '');
        $slug = $base;
        if (Product::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base.'-'.$vendorSlug, 110, '');
        }
        $root = $slug;
        for ($n = 2; Product::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $root.'-'.$n;
        }

        return $slug;
    }
}
