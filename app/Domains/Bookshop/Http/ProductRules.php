<?php

namespace App\Domains\Bookshop\Http;

/**
 * What a vendor may send for a product (BOOKSHOP_PLAN §5). The shape of the
 * input only; what makes a product *valid* (unique SKU within the vendor,
 * a "was" price above the price, photo and variant limits) is in
 * `SaveVendorProductAction`.
 */
final class ProductRules
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        $kb = (int) config('bookshop.photos.max_kilobytes', 5120);
        $maxPhotos = (int) config('bookshop.photos.max_per_product', 8);
        $maxVariants = (int) config('bookshop.variants.max_per_product', 30);

        return [
            'title' => 'required|string|max:255',
            'title_dv' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'summary' => 'nullable|string|max:500',
            'summary_dv' => 'nullable|string|max:500',
            'summary_ar' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:20000',
            'description_dv' => 'nullable|string|max:20000',
            'description_ar' => 'nullable|string|max:20000',
            'product_category_id' => 'nullable|integer|exists:product_categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'nullable|string|max:60',
            'price' => 'required|numeric|min:0|max:1000000',
            'compare_at_price' => 'nullable|numeric|min:0|max:1000000',
            'cost' => 'nullable|numeric|min:0|max:1000000',
            'tax_class' => 'required|string|in:standard,zero_rated,exempt',
            'sku' => 'nullable|string|max:64',
            'barcode' => 'nullable|string|max:64',
            'weight_grams' => 'nullable|integer|min:0|max:1000000',
            'dimensions' => 'nullable|string|max:60',
            'track_stock' => 'nullable|boolean',
            'stock' => 'nullable|integer|min:0|max:1000000',
            'low_stock_at' => 'nullable|integer|min:0|max:100000',
            'lead_days' => 'nullable|integer|min:0|max:365',
            'status' => 'required|string|in:draft,active,archived',
            'visibility' => 'required|string|in:shop,storefront',
            'details' => 'nullable|array',
            'details.*' => 'nullable|string|max:255',
            // A form that removes every variant sends none, so it says so.
            'variants_sent' => 'nullable|boolean',
            'variants' => "nullable|array|max:{$maxVariants}",
            'variants.*.id' => 'nullable|integer',
            'variants.*.name' => 'nullable|string|max:120',
            'variants.*.sku' => 'nullable|string|max:64',
            'variants.*.price' => 'nullable|numeric|min:0|max:1000000',
            'variants.*.stock' => 'nullable|integer|min:0|max:1000000',
            'variants.*.is_active' => 'nullable|boolean',
            'photos' => "nullable|array|max:{$maxPhotos}",
            'photos.*' => "file|mimes:jpeg,jpg,png,webp|max:{$kb}",
        ];
    }
}
