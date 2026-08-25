<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Models\CourseOffering;

class DefaultSelfLearningOfferingAction
{
    /**
     * @return array{id: int, course_id: int}|null
     */
    public function execute(int $courseId): ?array
    {
        $offering = CourseOffering::query()
            ->where('course_id', $courseId)
            ->where('delivery_mode', DeliveryMode::SelfLearning)
            ->orderBy('id')
            ->first();

        if ($offering === null) {
            return null;
        }

        return [
            'id' => $offering->id,
            'course_id' => $offering->course_id,
        ];
    }
}
