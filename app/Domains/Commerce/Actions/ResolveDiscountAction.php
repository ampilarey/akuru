<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

/**
 * §35.9 validation + §43.15: a discount reduces the price, nothing else.
 * Limits count pending+confirmed redemptions (released ones freed the slot).
 */
class ResolveDiscountAction
{
    /**
     * @return array{discount_code: DiscountCode, amount_discounted: float, final_amount: float}
     */
    public function execute(string $code, int $userId, float $orderAmount, bool $payingWithWallet = false): array
    {
        $discount = DiscountCode::query()->where('code', trim($code))->first();
        if ($discount === null || $discount->status !== 'active') {
            throw ValidationException::withMessages(['discount_code' => 'Discount code not found or inactive.']);
        }
        if ($discount->starts_at !== null && $discount->starts_at->isFuture()) {
            throw ValidationException::withMessages(['discount_code' => 'Discount code is not active yet.']);
        }
        if ($discount->ends_at !== null && $discount->ends_at->isPast()) {
            throw ValidationException::withMessages(['discount_code' => 'Discount code has ended.']);
        }
        if ($payingWithWallet && ! $discount->can_use_with_wallet) {
            throw ValidationException::withMessages(['discount_code' => 'This code cannot be combined with wallet payment.']);
        }
        if ($discount->minimum_order_amount !== null && $orderAmount < (float) $discount->minimum_order_amount) {
            throw ValidationException::withMessages(['discount_code' => 'Order is below this code\'s minimum amount.']);
        }

        $counting = fn ($query) => $query->whereIn('status', ['pending', 'confirmed']);
        if ($discount->usage_limit !== null
            && $counting($discount->redemptions())->count() >= $discount->usage_limit) {
            throw ValidationException::withMessages(['discount_code' => 'Discount code has been fully used.']);
        }
        if ($discount->per_user_limit !== null
            && $counting($discount->redemptions()->where('user_id', $userId))->count() >= $discount->per_user_limit) {
            throw ValidationException::withMessages(['discount_code' => 'You have already used this code.']);
        }

        $off = $discount->discount_type?->value === 'percentage'
            ? $orderAmount * ((float) $discount->discount_value / 100)
            : (float) $discount->discount_value;
        if ($discount->max_discount_amount !== null) {
            $off = min($off, (float) $discount->max_discount_amount);
        }
        $off = round(min($off, $orderAmount), 2);

        return [
            'discount_code' => $discount,
            'amount_discounted' => $off,
            'final_amount' => round($orderAmount - $off, 2),
        ];
    }
}
