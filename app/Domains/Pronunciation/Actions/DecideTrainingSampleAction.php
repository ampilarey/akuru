<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\TrainingSample;
use Illuminate\Validation\ValidationException;

/**
 * §51.16/§51.17: only APPROVED samples ever reach the dataset; noisy or
 * doubtful audio is rejected with a reason. Pending samples only.
 */
class DecideTrainingSampleAction
{
    public function execute(int $sampleId, int $decidedBy, bool $approve, ?string $reason = null): TrainingSample
    {
        $sample = TrainingSample::query()->findOrFail($sampleId);
        if ($sample->status !== 'pending_review') {
            throw ValidationException::withMessages(['sample' => 'This sample has already been decided.']);
        }

        $sample->fill([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by_user_id' => $approve ? $decidedBy : null,
            'rejection_reason' => $approve ? null : ($reason ?? 'Rejected'),
        ])->save();

        return $sample->refresh();
    }
}
