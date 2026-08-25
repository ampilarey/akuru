<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Offerings\Models\CourseOffering;

class ListCourseOfferingsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $courses = app(ListEngineCoursesAction::class)->execute()->keyBy('id');

        return [
            'courses' => $courses->values()->all(),
            'modes' => array_map(fn ($mode) => $mode->value, \App\Domains\Offerings\Enums\DeliveryMode::cases()),
            'rows' => CourseOffering::query()
                ->orderBy('title')
                ->get()
                ->map(fn (CourseOffering $offering) => [
                    'id' => $offering->id,
                    'course_id' => $offering->course_id,
                    'course_title' => $courses[$offering->course_id]['title'] ?? '',
                    'title' => $offering->title,
                    'delivery_mode' => $offering->delivery_mode?->value ?? $offering->delivery_mode,
                    'status' => $offering->status?->value ?? $offering->status,
                    'pin_mode' => $offering->pin_mode,
                    'pinned_at' => $offering->pinned_at?->toIso8601String(),
                    'seat_limit' => $offering->seat_limit,
                ])
                ->values()
                ->all(),
        ];
    }
}
