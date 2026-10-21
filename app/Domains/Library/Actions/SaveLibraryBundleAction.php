<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryBundle;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * LIBRARY_PLAN §19 (B4d, STATUS §5un): the office makes or changes a bundle —
 * two to twenty paid, published works at one price below what they cost
 * apart. A bundle that saves nothing is not an offer.
 */
class SaveLibraryBundleAction
{
    public const MIN_ITEMS = 2;

    public const MAX_ITEMS = 20;

    /**
     * @param  array{title: string, title_dv?: ?string, title_ar?: ?string, description?: ?string, price: mixed, item_ids: list<int>, is_active?: mixed, ends_at?: ?string}  $data
     */
    public function execute(array $data, ?LibraryBundle $bundle, int $byUserId): LibraryBundle
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($data['item_ids'] ?? []))));
        $items = LibraryItem::query()->whereIn('id', $ids)->where('status', 'published')->where('access_type', 'paid')->where('price', '>', 0)->get()->keyBy('id');
        if (count($ids) < self::MIN_ITEMS || count($ids) > self::MAX_ITEMS || $items->count() !== count($ids)) {
            throw ValidationException::withMessages(['item_ids' => __('admin.library_bundles_error_items', ['min' => self::MIN_ITEMS, 'max' => self::MAX_ITEMS])]);
        }
        $price = round((float) $data['price'], 2);
        $listTotal = round((float) $items->sum(fn (LibraryItem $i) => (float) $i->price), 2);
        if ($price <= 0 || $price >= $listTotal) {
            throw ValidationException::withMessages(['price' => __('admin.library_bundles_error_price', ['total' => number_format($listTotal, 2)])]);
        }

        return DB::transaction(function () use ($data, $bundle, $byUserId, $ids, $price) {
            $bundle ??= new LibraryBundle(['created_by' => $byUserId, 'slug' => $this->slug((string) $data['title'])]);
            $bundle->fill([
                'title' => trim((string) $data['title']),
                'title_dv' => trim((string) ($data['title_dv'] ?? '')) ?: null,
                'title_ar' => trim((string) ($data['title_ar'] ?? '')) ?: null,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'price' => $price,
                'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'ends_at' => ! empty($data['ends_at']) ? $data['ends_at'] : null,
            ])->save();
            $bundle->items()->sync(collect($ids)->mapWithKeys(fn (int $id, int $i) => [$id => ['sort_order' => $i]])->all());

            return $bundle->refresh();
        });
    }

    private function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'bundle';
        $slug = $base;
        for ($n = 2; LibraryBundle::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
