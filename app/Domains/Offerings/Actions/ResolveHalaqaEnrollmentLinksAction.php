<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\OfferingHalaqaEnrollmentLink;
use App\Support\Contracts\HalaqaReferenceReader;

/**
 * F2 seam for the Quran component: the program's enrollment mapping as plain
 * arrays, so milestone→completion sync never touches Offerings or Courses
 * models across the component boundary (rule 3).
 */
class ResolveHalaqaEnrollmentLinksAction
{
    /**
     * @return list<array{hifz_enrollment_id: int, course_enrollment_id: int, student_id: int}>
     */
    public function execute(int $hifzProgramId): array
    {
        $enrollments = app(HalaqaReferenceReader::class)->listEnrollments($hifzProgramId);
        if ($enrollments === []) {
            return [];
        }

        $byHifzId = OfferingHalaqaEnrollmentLink::query()
            ->whereIn('hifz_enrollment_id', array_column($enrollments, 'id'))
            ->get()
            ->keyBy('hifz_enrollment_id');

        $links = [];
        foreach ($enrollments as $enrollment) {
            $link = $byHifzId->get((int) $enrollment['id']);
            if ($link === null) {
                continue;
            }
            $links[] = [
                'hifz_enrollment_id' => (int) $enrollment['id'],
                'course_enrollment_id' => (int) $link->course_enrollment_id,
                'student_id' => (int) $enrollment['student_id'],
            ];
        }

        return $links;
    }
}
