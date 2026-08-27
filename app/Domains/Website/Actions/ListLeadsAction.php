<?php

namespace App\Domains\Website\Actions;

use App\Domains\Courses\Actions\PresentCourseTitlesAction;
use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Enums\LeadStatus;
use App\Domains\Website\Models\Lead;
use Illuminate\Support\Collection;

class ListLeadsAction
{
    /**
     * @param  array{source?: string, status?: string, course_id?: int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $query = Lead::query()->orderByDesc('created_at');

        if (! empty($filters['source'])) {
            $source = LeadSource::tryFrom((string) $filters['source']);
            if ($source !== null) {
                $query->where('source', $source);
            }
        }
        if (! empty($filters['status'])) {
            $status = LeadStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }
        if (! empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        $leads = $query->get();
        $titles = app(PresentCourseTitlesAction::class)->execute($leads->pluck('course_id')->all());

        return $leads->map(function (Lead $lead) use ($titles) {
            return [
                'id' => $lead->id,
                'course_id' => $lead->course_id,
                'course_title' => $titles[$lead->course_id] ?? ('#'.$lead->course_id),
                'name' => $lead->name,
                'mobile' => $lead->mobile,
                'email' => $lead->email,
                'source' => $lead->source->value,
                'status' => $lead->status->value,
                'notes' => $lead->notes,
                'created_at' => $lead->created_at?->timezone(config('app.timezone'))->toDateTimeString(),
            ];
        })->values();
    }
}
