<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\JobApplication;
use Illuminate\Support\Collection;

class ListJobApplicationsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $jobPostingId = null): Collection
    {
        return JobApplication::query()
            ->with('posting')
            ->when($jobPostingId, fn ($query) => $query->where('job_posting_id', $jobPostingId))
            ->orderByDesc('id')
            ->get()
            ->map(fn (JobApplication $row) => [
                'id' => $row->id,
                'job_posting_id' => $row->job_posting_id,
                'job_title' => $row->posting?->title,
                'name' => $row->name,
                'mobile' => $row->mobile,
                'email' => $row->email,
                'cover_note' => $row->cover_note,
                'status' => $row->status?->value ?? $row->status,
                'stage_notes' => $row->stage_notes ?? [],
            ])
            ->values();
    }
}
