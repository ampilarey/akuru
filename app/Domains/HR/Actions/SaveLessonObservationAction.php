<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LessonObservation;

class SaveLessonObservationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): LessonObservation
    {
        return LessonObservation::query()->create([
            'staff_profile_id' => (int) $data['staff_profile_id'],
            'observer_id' => $data['observer_id'] ?? null,
            'date' => $data['date'],
            'class_id' => $data['class_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'criteria' => $data['criteria'] ?? [],
            'summary' => $data['summary'] ?? null,
            'shared_with_staff' => (bool) ($data['shared_with_staff'] ?? false),
        ]);
    }
}
