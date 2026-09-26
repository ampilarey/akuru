<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

/**
 * BOOKSHOP_PLAN B7 (§6.5 "discount codes funded by the vendor, scoped to
 * their products — Commerce's discount codes with a vendor scope"): the
 * one discount system (rule 11), with codes owned by one scope. Every read
 * and write here is confined to the scope it is handed, so a seller can
 * only ever see or change its own codes; they are always funded by that
 * seller. Commerce knows the scope only as a type and an id.
 */
class ManageScopedDiscountCodesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $scopeType, int $scopeId): array
    {
        return DiscountCode::query()->where('applies_to_type', $scopeType)->where('applies_to_id', $scopeId)
            ->withCount(['redemptions as used_count' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->withSum(['redemptions as discounted_total' => fn ($q) => $q->where('status', 'confirmed')], 'amount_discounted')
            ->orderByDesc('id')->get()
            ->map(fn (DiscountCode $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'discount_type' => $c->discount_type?->value,
                'discount_value' => (string) $c->discount_value,
                'max_discount_amount' => $c->max_discount_amount !== null ? (string) $c->max_discount_amount : null,
                'minimum_order_amount' => $c->minimum_order_amount !== null ? (string) $c->minimum_order_amount : null,
                'usage_limit' => $c->usage_limit,
                'per_user_limit' => $c->per_user_limit,
                'starts_at' => $c->starts_at?->toDateString(),
                'ends_at' => $c->ends_at?->toDateString(),
                'status' => $c->status,
                'used_count' => (int) $c->used_count,
                'discounted_total' => number_format((float) ($c->discounted_total ?? 0), 2, '.', ''),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $scopeType, int $scopeId, array $data, ?int $codeId, int $userId): int
    {
        $existing = $codeId === null ? null : $this->find($scopeType, $scopeId, $codeId);
        $code = app(SaveDiscountCodeAction::class)->execute([
            'applies_to_type' => $scopeType,
            'applies_to_id' => $scopeId,
            'discount_funding_source' => $scopeType,
            'created_by' => $userId,
            'can_use_with_wallet' => true,
        ] + $data, $existing);

        return (int) $code->id;
    }

    public function setStatus(string $scopeType, int $scopeId, int $codeId, string $status): void
    {
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid status.']);
        }
        $this->find($scopeType, $scopeId, $codeId)->update(['status' => $status]);
    }

    private function find(string $scopeType, int $scopeId, int $codeId): DiscountCode
    {
        return DiscountCode::query()->where('applies_to_type', $scopeType)->where('applies_to_id', $scopeId)->whereKey($codeId)->firstOrFail();
    }
}
