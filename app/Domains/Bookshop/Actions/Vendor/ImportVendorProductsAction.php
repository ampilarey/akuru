<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Shop\CustomerListsAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Http\ProductRules;
use App\Domains\Bookshop\Models\Brand;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Support\ProductSheet;
use App\Domains\Bookshop\Support\StockLedger;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The shop's products and stock by spreadsheet (BOOKSHOP_PLAN §5 "Bulk:
 * CSV import/export of products and stock", slice B8) — so a vendor with
 * 500 items is not typing them one by one.
 *
 * Two steps, never one: `preview` reads the file, matches each row to one
 * of **this shop's** products (by `id`, then `sku`, then a variant's SKU),
 * checks it with the same rules as the product form and says what would
 * happen — new, changed (and which fields), a variant's price or stock,
 * unchanged, or an error — writing nothing. `apply` re-checks the same rows
 * and writes the good ones through `SaveVendorProductAction`, so a CSV
 * product is exactly a form product; stock changes land in the stock log as
 * `import`. Rows with errors are skipped, never half-written.
 *
 * A column left out of the file is left alone; a cell left empty clears an
 * optional field. Title, price, tax class, status and visibility are never
 * cleared by an empty cell. New variants and photos are added on the form.
 */
class ImportVendorProductsAction
{
    /**
     * Read and check the file; keep it for the apply step. Writes nothing.
     */
    public function preview(VendorScope $scope, UploadedFile $file): string
    {
        [$columns, $ignored, $rows] = $this->read($file);
        $token = Str::random(32);
        Cache::put($this->key($scope, $token), ['columns' => $columns, 'ignored' => $ignored, 'rows' => $rows, 'name' => $file->getClientOriginalName()],
            now()->addMinutes((int) config('bookshop.operations.import_preview_minutes', 30)));

        return $token;
    }

    /**
     * What a kept file would do, checked against the shop as it is now.
     * Null once it has been applied or has expired.
     *
     * @return array<string, mixed>|null
     */
    public function show(VendorScope $scope, string $token): ?array
    {
        $cached = Cache::get($this->key($scope, $token));
        if (! is_array($cached)) {
            return null;
        }
        $plan = $this->plan($scope, (array) $cached['columns'], (array) $cached['rows']);

        return [
            'token' => $token,
            'file' => $cached['name'],
            'columns' => $cached['columns'],
            'ignored' => $cached['ignored'] ?? [],
            'summary' => $this->summary($plan),
            'rows' => array_map(fn (array $r) => array_intersect_key($r, array_flip(['line', 'action', 'sku', 'title', 'changes', 'errors'])), array_slice($plan, 0, 300)),
        ];
    }

    /**
     * @return array{created: int, updated: int, variants: int, unchanged: int, skipped: int, failed: list<array{line: int, error: string}>}
     */
    public function apply(VendorScope $scope, string $token): array
    {
        $cached = Cache::pull($this->key($scope, $token));
        if (! is_array($cached)) {
            throw ValidationException::withMessages(['file' => __('shop.error_import_expired')]);
        }
        $plan = $this->plan($scope, (array) $cached['columns'], (array) $cached['rows']);
        $save = app(SaveVendorProductAction::class);
        $out = ['created' => 0, 'updated' => 0, 'variants' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => []];
        foreach ($plan as $row) {
            if ($row['errors'] !== []) {
                $out['skipped']++;

                continue;
            }
            try {
                match ($row['action']) {
                    'create' => $save->execute($scope, $row['input'], null, [], 'import'),
                    'update' => $save->execute($scope, $row['input'], (int) $row['product_id'], [], 'import'),
                    'variant' => $this->saveVariant($scope, (int) $row['variant_id'], $row['input']),
                    default => null,
                };
                $out[match ($row['action']) {
                    'create' => 'created', 'update' => 'updated', 'variant' => 'variants', default => 'unchanged',
                }]++;
            } catch (ValidationException $e) {
                $out['failed'][] = ['line' => $row['line'], 'error' => (string) collect($e->errors())->flatten()->first()];
            }
        }

        return $out;
    }

    /**
     * The shop's sheet, product by product with its variants under it.
     *
     * @return Generator<int, list<string|int|null>>
     */
    public function export(VendorScope $scope): Generator
    {
        $query = Product::query()->where('vendor_id', $scope->vendorId)->with(['variants', 'category:id,slug', 'brand:id,name'])->orderBy('id');
        foreach ($query->lazyById(200) as $product) {
            yield ProductSheet::productRow($product);
            foreach ($product->variants as $variant) {
                yield ProductSheet::variantRow($product, $variant);
            }
        }
    }

    /**
     * @return array{0: list<string>, 1: list<string>, 2: list<array{line: int, cells: array<string, string>}>}
     */
    private function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => __('shop.error_import_unreadable')]);
        }
        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn ($h) => str_replace([' ', '-'], '_', strtolower(trim((string) $h))), str_getcsv(trim($first), $delimiter));
        $columns = array_values(array_intersect($header, ProductSheet::COLUMNS));
        $ignored = array_values(array_filter(array_diff($header, ProductSheet::COLUMNS), fn ($h) => $h !== ''));
        if (array_intersect($columns, ['id', 'sku', 'title']) === []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => __('shop.error_import_header')]);
        }

        $max = (int) config('bookshop.operations.import_max_rows', 2000);
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map(fn ($c) => trim((string) $c), $cells)) === '') {
                continue;
            }
            if (count($rows) >= $max) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => __('shop.error_import_too_many', ['max' => $max])]);
            }
            $row = [];
            foreach ($header as $i => $name) {
                if (in_array($name, $columns, true)) {
                    // Exports prefix a tab to a cell a spreadsheet would read as a formula; trim takes it off.
                    $row[$name] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[] = ['line' => $line, 'cells' => $row];
        }
        fclose($handle);
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('shop.error_import_empty')]);
        }

        return [$columns, $ignored, $rows];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{line: int, cells: array<string, string>}>  $rows
     * @return list<array<string, mixed>>
     */
    private function plan(VendorScope $scope, array $columns, array $rows): array
    {
        $products = Product::query()->where('vendor_id', $scope->vendorId)->get()->keyBy('id');
        $bySku = $products->filter(fn (Product $p) => $p->sku !== null && $p->sku !== '')->keyBy(fn (Product $p) => mb_strtolower($p->sku));
        $variants = ProductVariant::query()->whereIn('product_id', $products->keys())->get();
        $variantBySku = $variants->filter(fn (ProductVariant $v) => $v->sku !== null && $v->sku !== '')->keyBy(fn (ProductVariant $v) => mb_strtolower($v->sku));
        $variantByName = $variants->keyBy(fn (ProductVariant $v) => mb_strtolower((string) $products->get($v->product_id)?->sku).'|'.mb_strtolower($v->name));
        $categories = [];
        foreach (ProductCategory::query()->get(['id', 'slug', 'name']) as $c) {
            $categories[mb_strtolower($c->slug)] = $c->id;
            $categories[mb_strtolower($c->name)] = $c->id;
        }
        $brands = [];
        foreach (Brand::query()->get(['id', 'slug', 'name']) as $b) {
            $brands[mb_strtolower($b->slug)] = $b->id;
            $brands[mb_strtolower($b->name)] = $b->id;
        }

        $seen = [];
        $plan = [];
        foreach ($rows as $row) {
            $c = $row['cells'];
            $errors = [];
            $sku = $c['sku'] ?? '';
            $key = mb_strtolower($sku);
            $product = null;
            $variant = null;

            if (($c['id'] ?? '') !== '') {
                $product = ctype_digit($c['id']) ? $products->get((int) $c['id']) : null;
                if ($product === null) {
                    $errors[] = __('shop.import_no_id', ['id' => $c['id']]);
                }
            } elseif ($sku !== '' && ($c['variant'] ?? '') === '' && $bySku->has($key)) {
                $product = $bySku->get($key);
            } elseif ($sku !== '' && $variantBySku->has($key)) {
                $variant = $variantBySku->get($key);
            } elseif (($c['variant'] ?? '') !== '' && ($c['parent_sku'] ?? '') !== '') {
                $variant = $variantByName->get(mb_strtolower($c['parent_sku']).'|'.mb_strtolower($c['variant']));
            }

            if ($sku !== '') {
                if (isset($seen[$key])) {
                    $errors[] = __('shop.import_duplicate_sku', ['sku' => $sku, 'line' => $seen[$key]]);
                }
                $seen[$key] ??= $row['line'];
            }

            if ($variant !== null || ($c['variant'] ?? '') !== '') {
                $plan[] = $this->planVariant($row['line'], $c, $variant, $products, $errors);

                continue;
            }

            $isNew = $product === null;
            $input = $isNew ? ['title' => '', 'price' => null, 'tax_class' => 'standard', 'status' => 'draft', 'visibility' => 'shop', 'track_stock' => true, 'stock' => 0, 'tags' => [], 'details' => []] : $this->snapshot($product);
            $before = $input;
            $this->overlay($input, $c, $isNew, $categories, $brands, $errors);
            if ($isNew && $errors === [] && (trim((string) $input['title']) === '' || $input['price'] === null || $input['price'] === '')) {
                $errors[] = $sku !== '' ? __('shop.import_new_needs', ['sku' => $sku]) : __('shop.import_new_needs_plain');
            }
            if ($errors === []) {
                $errors = $this->validate($input, $bySku, $product?->id);
            }
            $changes = $isNew ? [] : $this->changes($before, $input);

            $plan[] = [
                'line' => $row['line'],
                'action' => $isNew ? 'create' : ($changes === [] ? 'unchanged' : 'update'),
                'sku' => $input['sku'] ?? $sku,
                'title' => (string) ($input['title'] ?? ''),
                'changes' => $changes,
                'errors' => $errors,
                'product_id' => $product?->id,
                'input' => $input,
            ];
        }

        return $plan;
    }

    /**
     * @param  array<string, string>  $c
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function planVariant(int $line, array $c, ?ProductVariant $variant, $products, array $errors): array
    {
        if ($variant === null) {
            $errors[] = __('shop.import_no_variant', ['name' => $c['variant'] ?? $c['sku'] ?? '']);
        }
        $input = [];
        $changes = [];
        if ($variant !== null && array_key_exists('price', $c)) {
            if ($c['price'] !== '' && (! is_numeric($c['price']) || (float) $c['price'] < 0)) {
                $errors[] = __('shop.import_bad_number', ['field' => 'price', 'value' => $c['price']]);
            } else {
                $input['price'] = $c['price'] === '' ? null : round((float) $c['price'], 2);
                if (! $this->same($variant->price, $input['price'])) {
                    $changes[] = 'price';
                }
            }
        }
        if ($variant !== null && array_key_exists('stock', $c) && $c['stock'] !== '') {
            if (! preg_match('/^\d+$/', $c['stock'])) {
                $errors[] = __('shop.import_bad_number', ['field' => 'stock', 'value' => $c['stock']]);
            } else {
                $input['stock'] = (int) $c['stock'];
                if ((int) $variant->stock !== $input['stock']) {
                    $changes[] = 'stock';
                }
            }
        }
        $parent = $variant !== null ? $products->get($variant->product_id) : null;

        return [
            'line' => $line,
            'action' => $changes === [] ? 'unchanged' : 'variant',
            'sku' => $variant?->sku ?? ($c['sku'] ?? ''),
            'title' => trim(($parent?->title ?? '').' · '.($variant?->name ?? ($c['variant'] ?? '')), ' ·'),
            'changes' => $changes,
            'errors' => $errors,
            'variant_id' => $variant?->id,
            'input' => $input,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $c
     * @param  array<string, int>  $categories
     * @param  array<string, int>  $brands
     * @param  list<string>  $errors
     */
    private function overlay(array &$input, array $c, bool $isNew, array $categories, array $brands, array &$errors): void
    {
        foreach (['title', 'title_dv', 'title_ar', 'summary', 'sku', 'barcode'] as $field) {
            if (array_key_exists($field, $c)) {
                if ($c[$field] === '' && $field === 'title') {
                    continue;
                }
                $input[$field] = $c[$field] === '' ? null : $c[$field];
            }
        }
        if (array_key_exists('price', $c) && $c['price'] !== '') {
            $input['price'] = $this->number($c['price'], 'price', $errors);
        }
        foreach (['compare_at_price', 'cost'] as $field) {
            if (array_key_exists($field, $c)) {
                $input[$field] = $c[$field] === '' ? null : $this->number($c[$field], $field, $errors);
            }
        }
        foreach (['stock', 'low_stock_at', 'lead_days', 'weight_grams'] as $field) {
            if (! array_key_exists($field, $c)) {
                continue;
            }
            if ($c[$field] === '') {
                if ($field !== 'stock') {
                    $input[$field] = null;
                }

                continue;
            }
            if (! preg_match('/^\d+$/', $c[$field])) {
                $errors[] = __('shop.import_bad_number', ['field' => $field, 'value' => $c[$field]]);

                continue;
            }
            $input[$field] = (int) $c[$field];
        }
        $choices = [
            'tax_class' => ['standard' => 'standard', 'zero_rated' => 'zero_rated', 'zero-rated' => 'zero_rated', 'zero rated' => 'zero_rated', 'exempt' => 'exempt'],
            'status' => ['draft' => 'draft', 'active' => 'active', 'archived' => 'archived'],
            'visibility' => ['shop' => 'shop', 'storefront' => 'storefront'],
        ];
        foreach ($choices as $field => $allowed) {
            if (array_key_exists($field, $c) && $c[$field] !== '') {
                $value = $allowed[mb_strtolower($c[$field])] ?? null;
                if ($value === null) {
                    $errors[] = __('shop.import_bad_choice', ['field' => $field, 'value' => $c[$field], 'allowed' => implode(', ', array_unique(array_values($allowed)))]);
                } else {
                    $input[$field] = $value;
                }
            }
        }
        if (array_key_exists('track_stock', $c) && $c['track_stock'] !== '') {
            $flag = mb_strtolower($c['track_stock']);
            if (in_array($flag, ['yes', 'y', 'true', '1'], true)) {
                $input['track_stock'] = true;
            } elseif (in_array($flag, ['no', 'n', 'false', '0'], true)) {
                $input['track_stock'] = false;
            } else {
                $errors[] = __('shop.import_bad_choice', ['field' => 'track_stock', 'value' => $c['track_stock'], 'allowed' => 'yes, no']);
            }
        }
        foreach (['category' => [$categories, 'product_category_id'], 'brand' => [$brands, 'brand_id']] as $field => [$map, $column]) {
            if (array_key_exists($field, $c)) {
                if ($c[$field] === '') {
                    $input[$column] = null;
                } elseif (isset($map[mb_strtolower($c[$field])])) {
                    $input[$column] = $map[mb_strtolower($c[$field])];
                } else {
                    $errors[] = __('shop.import_unknown_'.$field, ['value' => $c[$field]]);
                }
            }
        }
        if (array_key_exists('tags', $c)) {
            $input['tags'] = array_values(array_filter(array_map('trim', preg_split('/[;|]/', $c['tags']) ?: [])));
        }
        $details = (array) ($input['details'] ?? []);
        foreach (ProductSheet::DETAILS as $field) {
            if (array_key_exists($field, $c)) {
                if ($c[$field] === '') {
                    unset($details[$field]);
                } else {
                    $details[$field] = $c[$field];
                }
            }
        }
        $input['details'] = $details;
    }

    /**
     * @param  list<string>  $errors
     */
    private function number(string $value, string $field, array &$errors): ?float
    {
        $clean = str_replace([',', ' '], '', $value);
        if (! is_numeric($clean) || (float) $clean < 0) {
            $errors[] = __('shop.import_bad_number', ['field' => $field, 'value' => $value]);

            return null;
        }

        return round((float) $clean, 2);
    }

    /**
     * The same rules as the product form, and the same two it checks in the
     * Action: a "was" price above the price, and a SKU nobody else in the
     * shop has.
     *
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function validate(array $input, $bySku, ?int $productId): array
    {
        $rules = ProductRules::rules();
        unset($rules['photos'], $rules['photos.*'], $rules['variants'], $rules['variants_sent']);
        foreach (array_keys($rules) as $key) {
            if (str_starts_with($key, 'variants.')) {
                unset($rules[$key]);
            }
        }
        // Resolved against the preloaded lists already; no query per row.
        $rules['product_category_id'] = 'nullable|integer';
        $rules['brand_id'] = 'nullable|integer';
        $errors = Validator::make($input, $rules)->errors()->all();

        if (($input['compare_at_price'] ?? null) !== null && $input['price'] !== null && (float) $input['compare_at_price'] <= (float) $input['price']) {
            $errors[] = __('shop.error_compare_at');
        }
        $sku = mb_strtolower((string) ($input['sku'] ?? ''));
        if ($sku !== '' && $bySku->has($sku) && (int) $bySku->get($sku)->id !== (int) $productId) {
            $errors[] = __('shop.error_sku_taken');
        }

        return array_values(array_unique($errors));
    }

    /**
     * What the form would send for this product today.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Product $p): array
    {
        return [
            'title' => $p->title, 'title_dv' => $p->title_dv, 'title_ar' => $p->title_ar,
            'summary' => $p->summary, 'summary_dv' => $p->summary_dv, 'summary_ar' => $p->summary_ar,
            'description' => $p->description, 'description_dv' => $p->description_dv, 'description_ar' => $p->description_ar,
            'product_category_id' => $p->product_category_id, 'brand_id' => $p->brand_id,
            'price' => (float) $p->price,
            'compare_at_price' => $p->compare_at_price !== null ? (float) $p->compare_at_price : null,
            'cost' => $p->cost !== null ? (float) $p->cost : null,
            'tax_class' => $p->tax_class->value, 'sku' => $p->sku, 'barcode' => $p->barcode,
            'weight_grams' => $p->weight_grams, 'dimensions' => $p->dimensions,
            'track_stock' => (bool) $p->track_stock, 'stock' => (int) $p->stock,
            'low_stock_at' => $p->low_stock_at, 'lead_days' => $p->lead_days,
            'status' => $p->status->value, 'visibility' => $p->visibility->value,
            'tags' => array_values((array) ($p->tags ?? [])), 'details' => (array) ($p->details ?? []),
            'badge' => $p->badge, 'badge_dv' => $p->badge_dv, 'badge_ar' => $p->badge_ar,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changes(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $key => $value) {
            if (! $this->same($before[$key] ?? null, $value)) {
                $out[] = $key === 'product_category_id' ? 'category' : ($key === 'brand_id' ? 'brand' : $key);
            }
        }

        return $out;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a ?? []) === json_encode($b ?? []);
        }
        if (($a === null || $a === '') && ($b === null || $b === '')) {
            return true;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.001;
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveVariant(VendorScope $scope, int $variantId, array $input): void
    {
        $productId = DB::transaction(function () use ($scope, $variantId, $input) {
            $variant = ProductVariant::query()->whereKey($variantId)->lockForUpdate()->firstOrFail();
            $product = Product::query()->where('vendor_id', $scope->vendorId)->whereKey($variant->product_id)->lockForUpdate()->firstOrFail();
            $before = (int) $variant->stock;
            $variant->fill(array_intersect_key($input, array_flip(['price', 'stock'])))->save();
            StockLedger::record($product, $variant, (int) $variant->stock - $before, 'import', $scope->userId);

            return (int) $product->id;
        });
        app(CustomerListsAction::class)->notifyIfBack($productId);
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     * @return array<string, int>
     */
    private function summary(array $plan): array
    {
        $out = ['create' => 0, 'update' => 0, 'variant' => 0, 'unchanged' => 0, 'errors' => 0, 'total' => count($plan)];
        foreach ($plan as $row) {
            if ($row['errors'] !== []) {
                $out['errors']++;
            } else {
                $out[$row['action']]++;
            }
        }

        return $out;
    }

    private function key(VendorScope $scope, string $token): string
    {
        return 'bookshop.import.'.$scope->vendorId.'.'.$scope->userId.'.'.preg_replace('/[^A-Za-z0-9]/', '', $token);
    }
}
