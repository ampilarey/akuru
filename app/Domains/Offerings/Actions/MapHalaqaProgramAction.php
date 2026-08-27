<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F2 structure mapping: one Hifz program becomes one engine Course
 * (course_type 'hifz', subject 'hifz') with one face-to-face Offering,
 * linked through the A.3 OfferingHalaqaLink. Idempotent — an already-linked
 * program is returned untouched, including links made by hand through the
 * Sessions picker; the migration never re-parents an operator's mapping.
 *
 * dual_write stays false on created links: mapping structure is not the
 * decision to start mirroring ongoing writes (rule 9 — that switch stays
 * with config quran.halaqa_dual_write and the link flag).
 */
class MapHalaqaProgramAction
{
    /**
     * @return array{course_id: int, offering_id: int, link_id: int, created: bool}
     */
    public function execute(int $hifzProgramId, ?int $createdBy = null): array
    {
        $existing = OfferingHalaqaLink::query()
            ->where('hifz_program_id', $hifzProgramId)
            ->first();
        if ($existing !== null) {
            $offering = CourseOffering::query()->findOrFail($existing->course_offering_id);

            return [
                'course_id' => (int) $offering->course_id,
                'offering_id' => (int) $offering->id,
                'link_id' => (int) $existing->id,
                'created' => false,
            ];
        }

        $program = app(HalaqaReferenceReader::class)->findProgram($hifzProgramId);
        if ($program === null) {
            throw ValidationException::withMessages([
                'hifz_program_id' => ['Unknown Hifz program.'],
            ]);
        }

        $course = app(SaveEngineCourseAction::class)->execute([
            'title' => (string) $program['name'],
            'course_type' => 'hifz',
            'subject_id' => DB::table('course_subjects')->where('slug', 'hifz')->value('id'),
            'created_by' => $createdBy,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'title' => (string) $program['name'],
            'slug' => 'halaqa-'.$hifzProgramId,
            'delivery_mode' => 'face_to_face',
            'status' => 'open',
            'pin_mode' => 'latest',
            'academic_year_id' => $program['academic_year_id'] ?? null,
            'created_by' => $createdBy,
        ]);

        $link = OfferingHalaqaLink::query()->create([
            'course_offering_id' => $offering->id,
            'hifz_program_id' => $hifzProgramId,
            'academic_year_id' => $program['academic_year_id'] ?? null,
            'dual_write' => false,
        ]);

        return [
            'course_id' => (int) $course->id,
            'offering_id' => (int) $offering->id,
            'link_id' => (int) $link->id,
            'created' => true,
        ];
    }
}
