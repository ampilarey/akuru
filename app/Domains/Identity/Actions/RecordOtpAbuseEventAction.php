<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\OtpAbuseEvent;
use App\Domains\Identity\Models\UserContact;

/**
 * SPEC §32: "OTP abuse event logging for admin review."
 *
 * Before this, a tripped limit threw a validation error at the person and left
 * no trace anywhere. An admin could not tell one confused parent from somebody
 * burning the school's SMS credit, which is precisely the case §32 names.
 *
 * The contact is hashed on the way in, with the last four characters kept so a
 * human can recognise a number in a list without the list being a phone book.
 * These rows are the ones most likely to be exported and mailed around while
 * somebody investigates.
 */
class RecordOtpAbuseEventAction
{
    public function execute(
        string $kind,
        UserContact $contact,
        string $purpose,
        int $observed,
        int $threshold,
    ): OtpAbuseEvent {
        return $this->forValue(
            $kind,
            (string) $contact->value,
            (string) $contact->type,
            $purpose,
            $observed,
            $threshold,
            $contact->user_id,
        );
    }

    /**
     * The new-registration path has no `UserContact` yet — the account does not
     * exist until the code is verified — and it is the most exposed surface of
     * the two, being reachable without signing in. It logs through here.
     */
    public function forValue(
        string $kind,
        string $value,
        string $channel,
        string $purpose,
        int $observed,
        int $threshold,
        ?int $userId = null,
    ): OtpAbuseEvent {
        return OtpAbuseEvent::query()->create([
            'kind' => $kind,
            'purpose' => $purpose,
            'channel' => $channel,
            'contact_hash' => hash('sha256', config('app.key').'|otp-abuse|'.$value),
            'contact_tail' => $value === '' ? null : mb_substr($value, -4),
            'user_id' => $userId,
            'observed' => $observed,
            'threshold' => $threshold,
            'occurred_at' => now('Indian/Maldives'),
        ]);
    }
}
