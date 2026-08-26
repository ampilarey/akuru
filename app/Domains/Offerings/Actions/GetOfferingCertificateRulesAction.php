<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;

class GetOfferingCertificateRulesAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $offeringId): array
    {
        $offering = CourseOffering::query()->find($offeringId);
        $rules = $offering?->certificate_rules;

        return is_array($rules) ? $rules : [];
    }
}
