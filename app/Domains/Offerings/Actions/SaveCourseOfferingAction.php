<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ResolveEngineCourseAction;
use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCourseOfferingAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseOffering $offering = null): CourseOffering
    {
        $course = app(ResolveEngineCourseAction::class)->execute((int) $data['course_id']);
        $mode = DeliveryMode::tryFrom((string) ($data['delivery_mode'] ?? ''));
        if ($mode === null) {
            throw ValidationException::withMessages(['delivery_mode' => 'Invalid delivery mode.']);
        }

        $title = (string) $data['title'];
        $slug = $data['slug'] ?? Str::slug($title);
        if ($slug === '') {
            $slug = 'offering-'.Str::lower(Str::random(6));
        }

        $exists = CourseOffering::query()
            ->where('course_id', $course['id'])
            ->where('slug', $slug)
            ->when($offering, fn ($query) => $query->where('id', '!=', $offering->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['slug' => 'Offering slug must be unique within the course.']);
        }

        $payload = [
            'course_id' => $course['id'],
            'title' => $title,
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'slug' => $slug,
            'delivery_mode' => $mode,
            'status' => OfferingStatus::tryFrom((string) ($data['status'] ?? OfferingStatus::Draft->value)) ?? OfferingStatus::Draft,
            'pin_mode' => in_array($data['pin_mode'] ?? 'latest', ['latest', 'pinned'], true) ? ($data['pin_mode'] ?? 'latest') : 'latest',
            'seat_limit' => isset($data['seat_limit']) && $data['seat_limit'] !== '' ? (int) $data['seat_limit'] : null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($offering === null) {
            return CourseOffering::query()->create($payload);
        }

        $offering->fill($payload);
        $offering->save();

        return $offering->refresh();
    }
}
