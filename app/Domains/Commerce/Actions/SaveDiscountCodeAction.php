<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Enums\DiscountType;
use App\Domains\Commerce\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

class SaveDiscountCodeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?DiscountCode $code = null): DiscountCode
    {
        $type = DiscountType::tryFrom((string) ($data['discount_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['discount_type' => 'Invalid discount type.']);
        }
        $value = (float) ($data['discount_value'] ?? 0);
        if ($value <= 0 || ($type === DiscountType::Percentage && $value > 100)) {
            throw ValidationException::withMessages(['discount_value' => 'Invalid discount value.']);
        }

        $codeString = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($codeString === '') {
            throw ValidationException::withMessages(['code' => 'Code is required.']);
        }
        $exists = DiscountCode::query()
            ->where('code', $codeString)
            ->when($code, fn ($query) => $query->whereKeyNot($code->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => 'Code already exists.']);
        }

        $payload = [
            'code' => $codeString,
            'name' => (string) ($data['name'] ?? $codeString),
            'discount_type' => $type,
            'discount_value' => $value,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_user_limit' => $data['per_user_limit'] ?? null,
            'minimum_order_amount' => $data['minimum_order_amount'] ?? null,
            'can_use_with_wallet' => (bool) ($data['can_use_with_wallet'] ?? true),
            'status' => $data['status'] ?? 'active',
        ];

        // L6 (§21): who funds the discount — decides the writer's cut.
        if (in_array($data['discount_funding_source'] ?? null, ['shared', 'akuru', 'writer'], true)) {
            $payload['discount_funding_source'] = $data['discount_funding_source'];
        }

        if ($code === null) {
            return DiscountCode::query()->create($payload);
        }

        $code->fill($payload);
        $code->save();

        return $code->refresh();
    }
}
