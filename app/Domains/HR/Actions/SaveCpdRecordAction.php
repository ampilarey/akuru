<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\CpdRecord;

class SaveCpdRecordAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): CpdRecord
    {
        return CpdRecord::query()->create([
            'staff_profile_id' => (int) $data['staff_profile_id'],
            'title' => $data['title'],
            'provider' => $data['provider'] ?? null,
            'hours' => $data['hours'] ?? 0,
            'date' => $data['date'] ?? null,
            'certificate_document_id' => $data['certificate_document_id'] ?? null,
        ]);
    }
}
