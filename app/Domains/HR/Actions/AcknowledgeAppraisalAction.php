<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\AppraisalStatus;
use App\Domains\HR\Models\Appraisal;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use Illuminate\Validation\ValidationException;

class AcknowledgeAppraisalAction
{
    public function execute(int $appraisalId, int $userId, ?string $comment = null): Appraisal
    {
        $appraisal = Appraisal::query()->findOrFail($appraisalId);
        $profile = app(ResolveStaffProfileForUserAction::class)->execute($userId);

        if ($profile === null || (int) $profile['id'] !== (int) $appraisal->staff_profile_id) {
            throw ValidationException::withMessages(['appraisal' => 'You can only acknowledge your own appraisal.']);
        }

        $appraisal->status = AppraisalStatus::Acknowledged;
        $appraisal->acknowledged_at = now();
        $appraisal->staff_comment = $comment;
        $appraisal->save();

        return $appraisal->refresh();
    }
}
