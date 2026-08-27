<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\Testimonial;
use Illuminate\Support\Collection;

class ListCoursePageTestimonialsAction
{
    /**
     * Course-specific public testimonials first; if none, fall back to general
     * (course_id null) quotes. Never mixes the two lists.
     *
     * @return Collection<int, Testimonial>
     */
    public function execute(int $courseId, int $limit = 6): Collection
    {
        $own = Testimonial::query()
            ->public()
            ->where('course_id', $courseId)
            ->ordered()
            ->limit($limit)
            ->get();

        if ($own->isNotEmpty()) {
            return $own;
        }

        return Testimonial::query()
            ->public()
            ->whereNull('course_id')
            ->ordered()
            ->limit($limit)
            ->get();
    }
}
