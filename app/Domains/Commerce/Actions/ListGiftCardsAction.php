<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCard;

/**
 * Admin listing — the plain code is unrecoverable by design (§43.19), so
 * rows identify cards by recipient/amount/status only.
 */
class ListGiftCardsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        return GiftCard::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (GiftCard $card): array => [
                'id' => $card->id,
                'original_amount' => (string) $card->original_amount,
                'balance_amount' => (string) $card->balance_amount,
                'currency' => $card->currency,
                'recipient_name' => $card->recipient_name,
                'recipient_email' => $card->recipient_email,
                'status' => $card->status?->value,
                'expires_at' => $card->expires_at?->toDateString(),
                'created_at' => $card->created_at?->toDateString(),
            ])
            ->values()
            ->all();
    }
}
