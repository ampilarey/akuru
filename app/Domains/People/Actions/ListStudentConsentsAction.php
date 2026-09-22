<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Models\Consent;

/**
 * A pupil's consent ledger, newest first, as the profile's Consents tab shows
 * it. Rows are append-only (S1.3: a change is a new row, never an update), so
 * this lists history rather than current state; `HasActiveConsentAction`
 * answers the yes/no question.
 */
class ListStudentConsentsAction
{
    /**
     * @return list<array{id: int, consent_type: string, granted: bool, granted_at: ?string, revoked_at: ?string, source: string}>
     */
    public function execute(int $studentId): array
    {
        return Consent::query()
            ->where('person_type', ConsentPersonType::Student->value)
            ->where('person_id', $studentId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Consent $consent): array => [
                'id' => (int) $consent->id,
                'consent_type' => $consent->consent_type->value,
                'granted' => (bool) $consent->granted,
                'granted_at' => $consent->granted_at?->toDateTimeString(),
                'revoked_at' => $consent->revoked_at?->toDateTimeString(),
                'source' => $consent->source->value,
            ])
            ->all();
    }
}
