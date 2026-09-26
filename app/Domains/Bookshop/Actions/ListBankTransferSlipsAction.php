<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\BankTransferSlip;

/**
 * The office's queue of bank-transfer slips (BOOKSHOP_PLAN §7 "Orders:
 * intervene"): waiting first, then decided, with the checkout and its
 * customer. People are named through the auth model from config (rule 3).
 *
 * @return list<array<string, mixed>>
 */
class ListBankTransferSlipsAction
{
    public function execute(int $limit = 200): array
    {
        $slips = BankTransferSlip::query()->with('checkout')
            ->orderByRaw("case when status = 'waiting' then 0 else 1 end")
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $slips->pluck('checkout.user_id')->unique()->all())->get(['id', 'name', 'email'])->keyBy('id');

        return $slips->map(fn (BankTransferSlip $s) => [
            'id' => $s->id,
            'status' => $s->status->value,
            'checkout_number' => $s->checkout->number,
            'checkout_status' => $s->checkout->status->value,
            'total' => (string) $s->checkout->total,
            'currency' => $s->checkout->currency,
            'customer' => $people->get($s->checkout->user_id)?->name ?? ('#'.$s->checkout->user_id),
            'customer_email' => $people->get($s->checkout->user_id)?->email,
            'reference' => $s->reference,
            'note' => $s->note,
            'decision_note' => $s->decision_note,
            'uploaded_at' => $s->created_at?->toDateTimeString(),
            'decided_at' => $s->decided_at?->toDateTimeString(),
        ])->values()->all();
    }
}
