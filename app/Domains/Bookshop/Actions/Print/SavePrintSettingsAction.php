<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §4.6 (NT1): the print desk's settings. Switching the
 * service on is refused while the host cannot print or no paper is priced;
 * the switch also makes the name-tags product active or a draft, so the
 * Bookstore shows it only while the desk can make it.
 */
class SavePrintSettingsAction
{
    /**
     * @param  array{enabled: mixed, lead_days: mixed, min_sheets: mixed, tiers?: mixed, cash_allowed?: mixed, watermark?: mixed, max_rows?: mixed, max_upload_kb?: mixed}  $data
     */
    public function execute(array $data): array
    {
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($enabled && ($why = PrintDesk::cannotEnableBecause()) !== null) {
            throw ValidationException::withMessages(['enabled' => __($why)]);
        }

        $tiers = PrintDesk::tiers(is_array($data['tiers'] ?? null) ? $data['tiers'] : []);
        $set = app(SetSettingAction::class);
        $values = [
            'enabled' => [$enabled, 'boolean'],
            'lead_days' => [max(0, (int) ($data['lead_days'] ?? 0)), 'string'],
            'min_sheets' => [max(1, (int) ($data['min_sheets'] ?? 1)), 'string'],
            'tiers' => [$tiers, 'json'],
            'cash_allowed' => [filter_var($data['cash_allowed'] ?? false, FILTER_VALIDATE_BOOLEAN), 'boolean'],
            'watermark' => [trim((string) ($data['watermark'] ?? '')) ?: (string) config('print.desk.watermark'), 'string'],
            'max_rows' => [max(1, (int) ($data['max_rows'] ?? config('print.desk.max_rows'))), 'string'],
            'max_upload_kb' => [max(256, (int) ($data['max_upload_kb'] ?? config('print.desk.max_upload_kb'))), 'string'],
        ];
        foreach ($values as $field => [$value, $type]) {
            $set->execute(PrintDesk::SETTINGS[$field], $value, $type, 'bookshop', 'Bookstore: print desk '.str_replace('_', ' ', $field));
        }

        [, $product] = PrintDesk::ensure();
        $product->status = $enabled ? ProductStatus::Active : ProductStatus::Draft;
        $product->save();
        if (! $enabled) {
            // PP2: the desk off takes every print product off sale.
            Product::query()->whereIn('slug', array_column(PrintProducts::KINDS, 'slug'))->update(['status' => ProductStatus::Draft->value]);
        }

        return PrintDesk::settings();
    }
}
