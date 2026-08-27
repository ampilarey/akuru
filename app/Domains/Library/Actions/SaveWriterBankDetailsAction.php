<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterBankDetail;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/** L6 (§35 writer_bank_details): where a payout goes. One row per writer. */
class SaveWriterBankDetailsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data): WriterBankDetail
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => 'An approved writer profile is required.']);
        }

        return WriterBankDetail::query()->updateOrCreate(
            ['writer_id' => $profile->id],
            [
                'bank_name' => trim((string) $data['bank_name']),
                'account_name' => trim((string) $data['account_name']),
                'account_number' => trim((string) $data['account_number']),
                'currency' => $data['currency'] ?? 'MVR',
            ],
        );
    }
}
