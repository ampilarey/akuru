<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;

class SaveCoursePublicCtaAction
{
    public function execute(int $courseId, ?string $whatsappNumber, mixed $syllabusMediaFileId): void
    {
        $course = Course::query()->findOrFail($courseId);
        $digits = preg_replace('/\D+/', '', (string) $whatsappNumber) ?? '';
        $mediaId = is_numeric($syllabusMediaFileId) ? (int) $syllabusMediaFileId : 0;

        $course->whatsapp_number = $digits !== '' ? $digits : null;
        $course->syllabus_media_file_id = $mediaId > 0 ? $mediaId : null;
        $course->save();
    }
}
