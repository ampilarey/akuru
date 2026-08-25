<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\JobPostingStatus;
use App\Domains\HR\Models\JobPosting;
use Illuminate\Support\Collection;

class ListJobPostingsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(bool $publicOnly = false): Collection
    {
        return JobPosting::query()
            ->when($publicOnly, fn ($query) => $query
                ->where('public', true)
                ->where('status', JobPostingStatus::Published)
            )
            ->orderByDesc('id')
            ->get()
            ->map(fn (JobPosting $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'title_arabic' => $row->title_arabic,
                'title_dhivehi' => $row->title_dhivehi,
                'description' => $row->description,
                'requirements' => $row->requirements,
                'department' => $row->department,
                'employment_type' => $row->employment_type,
                'closes_at' => $row->closes_at?->toDateString(),
                'status' => $row->status?->value ?? $row->status,
                'public' => $row->public,
            ])
            ->values();
    }
}
