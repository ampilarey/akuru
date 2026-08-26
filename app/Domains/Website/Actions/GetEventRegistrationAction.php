<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\EventRegistration;

class GetEventRegistrationAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $registrationId): ?array
    {
        $row = EventRegistration::query()->find($registrationId);
        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'event_id' => $row->event_id,
            'student_id' => $row->student_id,
            'parent_user_id' => $row->parent_user_id,
            'status' => $row->status,
        ];
    }
}
