<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\IssuedCertificate;
use Illuminate\Validation\ValidationException;

class RevokeIssuedCertificateAction
{
    public function execute(int $issuedId): IssuedCertificate
    {
        $row = IssuedCertificate::query()->findOrFail($issuedId);
        if ($row->revoked_at !== null) {
            throw ValidationException::withMessages(['id' => 'Certificate is already revoked.']);
        }
        $row->update(['revoked_at' => now()]);

        return $row->fresh();
    }
}
