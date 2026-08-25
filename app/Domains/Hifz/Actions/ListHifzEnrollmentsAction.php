<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzEnrollment;

class ListHifzEnrollmentsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $programId): array
    {
        return HifzEnrollment::query()
            ->where('hifz_program_id', $programId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (HifzEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student_id' => (int) $enrollment->student_id,
                'status' => $enrollment->status?->value ?? $enrollment->status,
            ])
            ->values()
            ->all();
    }
}
