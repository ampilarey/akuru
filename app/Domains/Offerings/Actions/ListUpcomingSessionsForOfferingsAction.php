<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOfferingSession;

class ListUpcomingSessionsForOfferingsAction
{
    /**
     * @param  list<int>  $offeringIds
     * @return list<array<string, mixed>>
     */
    public function execute(array $offeringIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $offeringIds)));
        if ($ids === []) {
            return [];
        }

        return CourseOfferingSession::query()
            ->whereIn('course_offering_id', $ids)
            ->where('starts_at', '>=', now()->subHour())
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(fn (CourseOfferingSession $session) => [
                'id' => $session->id,
                'course_offering_id' => $session->course_offering_id,
                'title' => $session->title,
                'session_type' => $session->session_type?->value ?? $session->session_type,
                'starts_at' => $session->starts_at?->toIso8601String(),
                'location_name' => $session->location_name,
                'online_meeting_url' => $session->online_meeting_url,
                'is_required' => $session->is_required,
            ])
            ->all();
    }
}
