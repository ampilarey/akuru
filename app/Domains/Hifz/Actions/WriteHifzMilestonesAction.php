<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Support\Contracts\HalaqaMilestoneWriter;
use Illuminate\Validation\ValidationException;

/**
 * Phase F5-P3 addition (freeze lifted for the §2b migration only): Hifz stays
 * the owner of hifz_milestones and enforces the workflow transitions; engine
 * surfaces drive it through the HalaqaMilestoneWriter contract.
 */
class WriteHifzMilestonesAction implements HalaqaMilestoneWriter
{
    public function recommend(array $data): array
    {
        $milestone = HifzMilestone::query()->create([
            'hifz_program_id' => (int) $data['hifz_program_id'],
            'student_id' => (int) $data['student_id'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'type' => (string) $data['type'],
            'surah_number' => $data['surah_number'] ?? null,
            'juz_number' => $data['juz_number'] ?? null,
            'page_number' => $data['page_number'] ?? null,
            'title' => $data['title'] ?? null,
            'completed_at' => $data['completed_at'] ?? now(),
            'recommended_by' => $data['recommended_by'] ?? null,
            'recommended_at' => now(),
            'status' => 'pending',
            'note' => $data['note'] ?? null,
            'created_by' => $data['recommended_by'] ?? null,
        ]);

        return $this->toArray($milestone);
    }

    public function review(int $milestoneId, int $reviewedBy, ?string $note = null): array
    {
        $milestone = HifzMilestone::query()->findOrFail($milestoneId);
        if (($milestone->status?->value ?? $milestone->status) !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending milestones can be supervisor-reviewed.',
            ]);
        }

        $milestone->fill([
            'status' => 'supervisor_reviewed',
            'supervisor_reviewed_by' => $reviewedBy,
            'supervisor_reviewed_at' => now(),
            'note' => $note ?? $milestone->note,
            'updated_by' => $reviewedBy,
        ]);
        $milestone->save();

        return $this->toArray($milestone->refresh());
    }

    public function decide(int $milestoneId, int $decidedBy, bool $approved, ?string $note = null): array
    {
        $milestone = HifzMilestone::query()->findOrFail($milestoneId);
        $status = $milestone->status?->value ?? $milestone->status;
        if (! in_array($status, ['pending', 'supervisor_reviewed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Milestone has already been decided.',
            ]);
        }

        $milestone->fill([
            'status' => $approved ? 'approved' : 'rejected',
            'approved_by' => $approved ? $decidedBy : null,
            'approved_at' => $approved ? now() : null,
            'note' => $note ?? $milestone->note,
            'updated_by' => $decidedBy,
        ]);
        $milestone->save();

        return $this->toArray($milestone->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(HifzMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'hifz_program_id' => (int) $milestone->hifz_program_id,
            'student_id' => (int) $milestone->student_id,
            'type' => $milestone->type?->value ?? $milestone->type,
            'title' => $milestone->title,
            'status' => $milestone->status?->value ?? $milestone->status,
        ];
    }
}
