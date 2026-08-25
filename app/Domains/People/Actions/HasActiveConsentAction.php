<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentType;
use Illuminate\Support\Facades\DB;

class HasActiveConsentAction
{
    public function execute(
        ConsentPersonType|string $personType,
        int $personId,
        ConsentType|string $consentType,
    ): bool {
        $personType = $personType instanceof ConsentPersonType ? $personType->value : $personType;
        $consentType = $consentType instanceof ConsentType ? $consentType->value : $consentType;

        $latest = DB::table('consents')
            ->where('person_type', $personType)
            ->where('person_id', $personId)
            ->where('consent_type', $consentType)
            ->orderByDesc('id')
            ->first();

        return $latest !== null && (bool) $latest->granted && $latest->revoked_at === null;
    }
}
