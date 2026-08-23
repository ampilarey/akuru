<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentSource;
use App\Domains\People\Enums\ConsentType;
use App\Domains\People\Models\Consent;
use InvalidArgumentException;

class RecordConsentAction
{
    public function execute(
        ConsentPersonType|string $personType,
        int $personId,
        ConsentType|string $consentType,
        bool $granted,
        int $grantedBy,
        ConsentSource|string $source,
    ): Consent {
        $personType = $personType instanceof ConsentPersonType
            ? $personType
            : ConsentPersonType::from($personType);
        $consentType = $consentType instanceof ConsentType
            ? $consentType
            : ConsentType::from($consentType);
        $source = $source instanceof ConsentSource
            ? $source
            : ConsentSource::from($source);

        if ($grantedBy < 1) {
            throw new InvalidArgumentException('granted_by is required.');
        }

        $latest = Consent::query()
            ->where('person_type', $personType->value)
            ->where('person_id', $personId)
            ->where('consent_type', $consentType->value)
            ->latest('id')
            ->first();

        if ($latest && $latest->granted === $granted) {
            return $latest;
        }

        return Consent::query()->create([
            'person_type' => $personType,
            'person_id' => $personId,
            'consent_type' => $consentType,
            'granted' => $granted,
            'granted_by' => $grantedBy,
            'granted_at' => now(),
            'revoked_at' => $granted ? null : now(),
            'source' => $source,
        ]);
    }
}
