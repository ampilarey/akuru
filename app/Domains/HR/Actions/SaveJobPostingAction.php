<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\JobPostingStatus;
use App\Domains\HR\Models\JobPosting;

class SaveJobPostingAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?JobPosting $posting = null): JobPosting
    {
        $payload = [
            'title' => $data['title'],
            'title_arabic' => $data['title_arabic'] ?? null,
            'title_dhivehi' => $data['title_dhivehi'] ?? null,
            'description' => $data['description'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'department' => $data['department'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => JobPostingStatus::tryFrom((string) ($data['status'] ?? 'draft')) ?? JobPostingStatus::Draft,
            'public' => (bool) ($data['public'] ?? false),
        ];

        if ($posting === null) {
            return JobPosting::query()->create($payload);
        }

        $posting->fill($payload);
        $posting->save();

        return $posting->refresh();
    }
}
