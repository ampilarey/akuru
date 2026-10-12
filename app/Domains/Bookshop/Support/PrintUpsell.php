<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Models\PrintTemplate;

/**
 * NAME_TAGS_PLAN §3.9 (NT7): the card on a school's book list and after
 * *Add all to cart* — *Add name tags and subject tags for this child*. It
 * shows only while the print desk is on and its product is for sale, and
 * starts the first design that prints subjects with the grade's subject set
 * chosen (`SavePrintJobAction::start`).
 */
final class PrintUpsell
{
    /** @return array{template: int, grade: ?string}|null */
    public static function card(?string $grade): ?array
    {
        if (! PrintDesk::settings()['enabled']) {
            return null;
        }
        $product = PrintDesk::product();
        if ($product === null || ! ListShopProductsAction::forSale()->whereKey($product->id)->exists()) {
            return null;
        }
        $template = PrintTemplate::query()->where('status', 'active')->whereIn('kind', ['subject_tag', 'both'])->orderBy('sort_order')->orderBy('id')->first()
            ?? PrintTemplate::query()->where('status', 'active')->orderBy('sort_order')->orderBy('id')->first();
        if ($template === null) {
            return null;
        }

        return ['template' => (int) $template->id, 'grade' => $grade !== null && trim($grade) !== '' ? trim($grade) : null];
    }
}
