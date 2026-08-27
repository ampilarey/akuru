<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/**
 * L5 (§43.1/§43.2): writers cannot publish directly and must be approved —
 * the application is the front door. One pending application per user;
 * the §31 writer agreement must be accepted at submission time.
 */
class ApplyAsWriterAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data): WriterApplication
    {
        if (WriterProfile::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['application' => 'You are already an approved writer.']);
        }
        if (WriterApplication::query()->where('user_id', $userId)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['application' => 'Your application is already pending review.']);
        }
        if (empty($data['agreement_accepted'])) {
            throw ValidationException::withMessages(['agreement_accepted' => 'You must accept the writer agreement.']);
        }

        return WriterApplication::query()->create([
            'user_id' => $userId,
            'display_name' => trim((string) $data['display_name']),
            'bio' => $data['bio'] ?? null,
            'qualifications' => $data['qualifications'] ?? null,
            'expertise' => $data['expertise'] ?? null,
            'motivation' => $data['motivation'] ?? null,
            'agreement_accepted_at' => now(),
            'status' => 'pending',
        ]);
    }
}
