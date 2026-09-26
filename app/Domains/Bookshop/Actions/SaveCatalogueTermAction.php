<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\Brand;
use App\Domains\Bookshop\Models\ProductCategory;
use Illuminate\Support\Str;

/**
 * The shared taxonomy every vendor files products under (BOOKSHOP_PLAN §7):
 * categories (trilingual) and brands. The office owns both; a vendor picks
 * from them, so "Stationery" is one shelf across the shop, not one per shop.
 */
class SaveCatalogueTermAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function category(array $data): ProductCategory
    {
        return ProductCategory::query()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'name_dv' => $data['name_dv'] ?? null,
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $this->uniqueSlug(ProductCategory::class, (string) $data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function brand(array $data): Brand
    {
        return Brand::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Brand::class, (string) $data['name']),
            'is_active' => true,
        ]);
    }

    /**
     * @param  class-string<ProductCategory|Brand>  $model
     */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'item', 70, '');
        $slug = $base;
        for ($n = 2; $model::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
