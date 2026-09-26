<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountCode;

/**
 * BOOKSHOP_PLAN B7: what a typed code applies to, before it is resolved, so
 * a checkout that sells for several sellers can price it against the right
 * goods (a vendor-funded code against that vendor's lines only). Reveals
 * the scope and who funds it — nothing a buyer could not learn by trying
 * the code — and null for a code that does not exist or is not active.
 */
class DescribeDiscountCodeAction
{
    /**
     * @return array{applies_to_type: string, applies_to_id: ?int, funding: string}|null
     */
    public function execute(string $code): ?array
    {
        $discount = DiscountCode::query()->where('code', strtoupper(trim($code)))->where('status', 'active')->first();
        if ($discount === null) {
            return null;
        }

        return [
            'applies_to_type' => (string) ($discount->applies_to_type ?: 'all'),
            'applies_to_id' => $discount->applies_to_id !== null ? (int) $discount->applies_to_id : null,
            'funding' => (string) ($discount->discount_funding_source ?: 'akuru'),
        ];
    }
}
