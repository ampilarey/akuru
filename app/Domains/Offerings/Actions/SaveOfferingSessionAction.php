<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\SessionType;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use Illuminate\Validation\ValidationException;

class SaveOfferingSessionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseOfferingSession $session = null): CourseOfferingSession
    {
        $offering = CourseOffering::query()->findOrFail((int) $data['course_offering_id']);
        $type = SessionType::tryFrom((string) ($data['session_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['session_type' => 'Invalid session type.']);
        }

        $starts = $data['starts_at'] ?? null;
        if ($starts === null || $starts === '') {
            throw ValidationException::withMessages(['starts_at' => 'Session start is required.']);
        }

        $payload = [
            'course_offering_id' => $offering->id,
            'academic_year_id' => $offering->academic_year_id,
            'term_id' => $offering->term_id,
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'session_type' => $type,
            'starts_at' => $starts,
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? 'Indian/Maldives',
            'location_name' => $data['location_name'] ?? null,
            'location_address' => $data['location_address'] ?? null,
            'online_meeting_url' => $data['online_meeting_url'] ?? null,
            'online_meeting_provider' => $data['online_meeting_provider'] ?? null,
            'teacher_user_id' => $data['teacher_user_id'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? true),
            'recording_url' => $data['recording_url'] ?? null,
            'materials' => $data['materials'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($session === null) {
            return CourseOfferingSession::query()->create($payload);
        }

        $session->fill($payload);
        $session->save();

        return $session->refresh();
    }
}
