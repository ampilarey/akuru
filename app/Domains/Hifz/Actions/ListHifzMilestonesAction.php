<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzMilestone;

class ListHifzMilestonesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $programId): array
    {
        return HifzMilestone::query()
            ->where('hifz_program_id', $programId)
            ->orderBy('id')
            ->get()
            ->map(fn (HifzMilestone $milestone): array => [
                'id' => $milestone->id,
                'student_id' => (int) $milestone->student_id,
                'type' => $milestone->type?->value ?? $milestone->type,
                'title' => $milestone->title,
                'status' => $milestone->status?->value ?? $milestone->status,
            ])
            ->values()
            ->all();
    }
}
