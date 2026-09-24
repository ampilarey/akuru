<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentGateCard;

/**
 * Which pupil a scanned code belongs to (E18).
 *
 * Accepts what a camera decodes (`AKG:TOKEN`), what a handheld scanner types
 * (the same, sometimes with a stray newline or a keyboard layout that turned
 * the colon into something else), and what a person types off the card
 * (`ABCD-EFGH-JKMN-PQRS`, any case).
 *
 * @return array{student_id: ?int, reason: ?string}
 */
class ResolveGateCardAction
{
    public function execute(string $code): array
    {
        $token = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (str_starts_with($token, 'AKG') && strlen($token) === 19) {
            $token = substr($token, 3);
        }

        if (strlen($token) !== 16) {
            return ['student_id' => null, 'reason' => 'That is not an Akuru gate card.'];
        }

        $card = StudentGateCard::query()->where('token', $token)->first();

        if ($card === null) {
            return ['student_id' => null, 'reason' => 'This card is not recognised.'];
        }

        if ($card->revoked_at !== null) {
            return ['student_id' => null, 'reason' => 'This card was replaced on '.$card->revoked_at->toDateString().' and no longer works. Ask the office for the new one.'];
        }

        return ['student_id' => (int) $card->student_id, 'reason' => null];
    }
}
