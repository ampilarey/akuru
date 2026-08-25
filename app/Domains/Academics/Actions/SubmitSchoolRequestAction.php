<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SchoolRequest;
use Illuminate\Validation\ValidationException;

class SubmitSchoolRequestAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): SchoolRequest
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return SchoolRequest::query()->create([
            'type' => SchoolRequestType::from((string) $data['type']),
            'requester_id' => (int) $data['requester_id'],
            'regarding_type' => $data['regarding_type'] ?? null,
            'regarding_id' => isset($data['regarding_id']) && $data['regarding_id'] !== ''
                ? (int) $data['regarding_id']
                : null,
            'payload' => $data['payload'] ?? [],
            'reason' => $reason,
            'status' => SchoolRequestStatus::Pending,
        ]);
    }
}
