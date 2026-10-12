<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintFinishingOption;
use Illuminate\Validation\ValidationException;

/** NAME_TAGS_PLAN §4.3 (NT1): a finishing and its price per sheet; retired, never deleted. */
class SavePrintFinishingAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(array $data, ?PrintFinishingOption $finishing = null): PrintFinishingOption
    {
        $active = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $price = round((float) ($data['price'] ?? 0), 2);
        if ($active && $price <= 0) {
            throw ValidationException::withMessages(['price' => __('print.error_finishing_price')]);
        }

        $finishing ??= new PrintFinishingOption;
        $finishing->fill([
            'name' => trim((string) $data['name']),
            'name_dv' => trim((string) ($data['name_dv'] ?? '')) ?: null,
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'price' => $price,
            'cuts_sheet' => filter_var($data['cuts_sheet'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_active' => $active,
            'sort_order' => (int) ($data['sort_order'] ?? $finishing->sort_order ?? 0),
        ]);
        $finishing->save();

        return $finishing;
    }
}
