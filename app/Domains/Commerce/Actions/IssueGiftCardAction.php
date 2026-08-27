<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCard;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * §43.19: the plain code exists only in this action's return value — the
 * database stores the SHA-256 hash. Whoever calls this shows the code
 * once and never persists it.
 */
class IssueGiftCardAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{gift_card: GiftCard, plain_code: string}
     */
    public function execute(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Gift card amount must be positive.']);
        }

        $plain = 'AKG-'.strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        $card = GiftCard::query()->create([
            'code_hash' => hash('sha256', $plain),
            'original_amount' => $amount,
            'balance_amount' => $amount,
            'currency' => $data['currency'] ?? 'MVR',
            'purchaser_user_id' => $data['purchaser_user_id'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'recipient_mobile' => $data['recipient_mobile'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'active',
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return ['gift_card' => $card, 'plain_code' => $plain];
    }
}
