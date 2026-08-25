<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;

class EnsureSelfLearningOfferingAction
{
    /**
     * @param  array{id: int, title: string}  $course
     */
    public function execute(array $course, ?int $createdBy = null): CourseOffering
    {
        $existing = CourseOffering::query()
            ->where('course_id', $course['id'])
            ->where('delivery_mode', DeliveryMode::SelfLearning)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return app(SaveCourseOfferingAction::class)->execute([
            'course_id' => $course['id'],
            'title' => $course['title'].' — Self-learning',
            'slug' => 'self-learning',
            'delivery_mode' => DeliveryMode::SelfLearning->value,
            'status' => OfferingStatus::Open->value,
            'pin_mode' => 'latest',
            'created_by' => $createdBy,
        ]);
    }
}
