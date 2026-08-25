<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\JobApplicationStatus;
use App\Domains\HR\Models\JobApplication;

class SaveJobApplicationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?JobApplication $application = null): JobApplication
    {
        $notes = $application?->stage_notes ?? [];
        $status = JobApplicationStatus::tryFrom((string) ($data['status'] ?? 'received')) ?? JobApplicationStatus::Received;

        if ($application === null || ($application->status !== $status)) {
            $notes[] = [
                'status' => $status->value,
                'at' => now()->toIso8601String(),
                'by' => $data['reviewed_by'] ?? null,
                'note' => $data['note'] ?? null,
            ];
        }

        $payload = [
            'job_posting_id' => (int) $data['job_posting_id'],
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'cv_document_id' => $data['cv_document_id'] ?? null,
            'cover_note' => $data['cover_note'] ?? null,
            'status' => $status,
            'stage_notes' => $notes,
            'reviewed_by' => $data['reviewed_by'] ?? null,
        ];

        if ($application === null) {
            return JobApplication::query()->create($payload);
        }

        $application->fill($payload);
        $application->save();

        return $application->refresh();
    }
}
