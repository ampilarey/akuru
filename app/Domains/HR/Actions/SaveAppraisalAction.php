<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\AppraisalStatus;
use App\Domains\HR\Models\Appraisal;

class SaveAppraisalAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Appraisal $appraisal = null): Appraisal
    {
        $payload = [
            'cycle_id' => (int) $data['cycle_id'],
            'staff_profile_id' => (int) $data['staff_profile_id'],
            'appraiser_id' => $data['appraiser_id'] ?? null,
            'ratings' => $data['ratings'] ?? [],
            'strengths' => $data['strengths'] ?? null,
            'development_areas' => $data['development_areas'] ?? null,
            'goals' => $data['goals'] ?? [],
            'status' => AppraisalStatus::tryFrom((string) ($data['status'] ?? 'draft')) ?? AppraisalStatus::Draft,
        ];

        if ($appraisal === null) {
            return Appraisal::query()->create($payload);
        }

        $appraisal->fill($payload);
        $appraisal->save();

        return $appraisal->refresh();
    }
}
