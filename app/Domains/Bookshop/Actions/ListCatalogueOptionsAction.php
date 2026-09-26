<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\TaxClass;
use App\Domains\Bookshop\Models\Brand;
use App\Domains\Bookshop\Models\ProductCategory;

/** What a product form offers: the shared categories and brands, and the fixed lists. */
class ListCatalogueOptionsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(bool $activeOnly = true): array
    {
        return [
            'categories' => ProductCategory::query()
                ->when($activeOnly, fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'name_dv' => $c->name_dv,
                    'name_ar' => $c->name_ar,
                    'slug' => $c->slug,
                    'parent_id' => $c->parent_id,
                ])->values()->all(),
            'brands' => Brand::query()
                ->when($activeOnly, fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name, 'slug' => $b->slug])
                ->values()->all(),
            'tax_classes' => array_map(fn (TaxClass $c) => $c->value, TaxClass::cases()),
            'statuses' => array_map(fn (ProductStatus $s) => $s->value, ProductStatus::cases()),
            'visibilities' => array_map(fn (ProductVisibility $v) => $v->value, ProductVisibility::cases()),
            'languages' => ['en' => 'English', 'dv' => 'Dhivehi', 'ar' => 'Arabic'],
        ];
    }
}
