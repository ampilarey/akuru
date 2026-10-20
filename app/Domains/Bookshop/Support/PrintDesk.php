<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Support\Contracts\PageImageInterface;
use App\Support\Contracts\PdfConverterInterface;

/**
 * NAME_TAGS_PLAN §4.6 (NT1): the print desk's knobs — the office's settings
 * over the deploy's defaults — and whether the service may be switched on
 * at all: only where this host can print a proof and a file, and only with
 * a paper priced and active to print on.
 */
final class PrintDesk
{
    public const SETTINGS = [
        'enabled' => 'bookshop_print_enabled',
        'lead_days' => 'bookshop_print_lead_days',
        'min_sheets' => 'bookshop_print_min_sheets',
        'tiers' => 'bookshop_print_tiers',
        'cash_allowed' => 'bookshop_print_cash_allowed',
        'watermark' => 'bookshop_print_watermark',
        'max_rows' => 'bookshop_print_max_rows',
        'max_upload_kb' => 'bookshop_print_max_upload_kb',
    ];

    public const PRODUCT_SLUG = 'name-tags-a4';

    /**
     * @return array{enabled: bool, lead_days: int, min_sheets: int, tiers: list<array{sheets: int, percent: float}>, cash_allowed: bool, watermark: string, max_rows: int, max_upload_kb: int}
     */
    public static function settings(): array
    {
        try {
            $stored = app(SettingsRepositoryInterface::class)->many(array_fill_keys(array_values(self::SETTINGS), null));
        } catch (\Throwable) {
            $stored = [];
        }
        $read = fn (string $key) => ($v = $stored[self::SETTINGS[$key]] ?? null) !== null && $v !== '' ? $v : config('print.desk.'.$key);
        $tiers = $read('tiers');
        if (is_string($tiers)) {
            $tiers = json_decode($tiers, true) ?: [];
        }

        return [
            'enabled' => filter_var($read('enabled'), FILTER_VALIDATE_BOOLEAN),
            'lead_days' => (int) $read('lead_days'),
            'min_sheets' => max(1, (int) $read('min_sheets')),
            'tiers' => self::tiers(is_array($tiers) ? $tiers : []),
            'cash_allowed' => filter_var($read('cash_allowed'), FILTER_VALIDATE_BOOLEAN),
            'watermark' => (string) $read('watermark'),
            'max_rows' => max(1, (int) $read('max_rows')),
            'max_upload_kb' => max(256, (int) $read('max_upload_kb')),
        ];
    }

    /** The quantity breaks, sorted, each `{sheets, percent}`. */
    public static function tiers(array $raw): array
    {
        $tiers = [];
        foreach ($raw as $tier) {
            $sheets = (int) ($tier['sheets'] ?? 0);
            $percent = (float) ($tier['percent'] ?? 0);
            if ($sheets > 1 && $percent > 0 && $percent < 100) {
                $tiers[$sheets] = ['sheets' => $sheets, 'percent' => round($percent, 2)];
            }
        }
        ksort($tiers);

        return array_values($tiers);
    }

    /** Why the service cannot be switched on, as a `print.*` phrase key — or null where it can. */
    public static function cannotEnableBecause(): ?string
    {
        if (! app(PdfConverterInterface::class)->enabled() || ! app(PageImageInterface::class)->enabled()) {
            return 'print.error_enable_no_chrome';
        }
        if (! PrintPaperOption::query()->where('is_active', true)->where(fn ($q) => $q->where('price_bw', '>', 0)->orWhere('price_colour', '>', 0))->exists()) {
            return 'print.error_enable_no_paper';
        }

        return null;
    }

    public static function vendor(): ?Vendor
    {
        return Vendor::query()->where('kind', 'akuru')->first();
    }

    /**
     * The Akuru vendor (plan §4.1), the *Print* category and the draft
     * products — *Name tags — A4* and, since PP2, the other print products
     * (`PrintProducts`) — made once, the first time the office opens
     * the print desk. Idempotent; a Bookstore that never opens it has no
     * Akuru shop in its lists.
     *
     * @return array{0: Vendor, 1: Product}
     */
    public static function ensure(): array
    {
        $vendor = self::vendor();
        if ($vendor === null) {
            $vendor = Vendor::query()->create([
                'name' => 'Akuru Print',
                'slug' => Vendor::query()->where('slug', 'akuru')->exists() ? 'akuru-print' : 'akuru',
                'code' => Vendor::query()->where('code', 'AKU')->exists() ? 'AKP' : 'AKU',
                'tagline' => 'Name tags and print, made by Akuru',
                'status' => 'active', 'kind' => 'akuru', 'fulfilment' => 'akuru', 'delivery_by' => 'akuru', 'commission_rate' => 0,
            ]);
            VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'collect_akuru', 'name' => 'Collect from Akuru Institute', 'fee' => 0, 'handling_days' => 2, 'is_active' => true, 'sort_order' => 1]);
        }
        $category = ProductCategory::query()->firstOrCreate(['slug' => 'print'], ['name' => 'Print', 'name_dv' => 'ޕްރިންޓް', 'name_ar' => 'طباعة', 'sort_order' => 20, 'is_active' => true]);
        // PP2: every print product, each a draft until it is put on sale.
        foreach (PrintProducts::KINDS as $meta) {
            Product::query()->where('slug', $meta['slug'])->exists() || Product::query()->create([
                'vendor_id' => $vendor->id, 'product_category_id' => $category->id, 'slug' => $meta['slug'],
                ...collect($meta)->only(['title', 'title_dv', 'title_ar', 'summary', 'summary_dv', 'summary_ar'])->all(),
                'price' => 0, 'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => false, 'stock' => 0, 'status' => 'draft', 'visibility' => 'shop',
            ]);
        }

        return [$vendor, self::product()];
    }

    public static function product(): ?Product
    {
        return Product::query()->where('slug', self::PRODUCT_SLUG)->first();
    }
}
