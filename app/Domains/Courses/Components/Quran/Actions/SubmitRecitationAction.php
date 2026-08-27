<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Actions\ResolveLatestEnrollmentIdAction;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentStatus;
use App\Domains\Courses\Components\Quran\Enums\RecitationMode;
use App\Domains\Courses\Components\Quran\Enums\RecitationSubmissionStatus;
use App\Domains\Courses\Components\Quran\Models\QuranHifzAssignment;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Support\Contracts\QuranReferenceReader;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §52.19 — a student's recitation lands as an engine-keyed submission.
 * Enrollment resolves through the F0 engine seam; surahs validate through
 * the reference contract (rule 11 — the existing dataset is the only one).
 */
class SubmitRecitationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): QuranRecitationSubmission
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $enrollmentId = isset($data['course_enrollment_id']) && $data['course_enrollment_id'] !== ''
            ? (int) $data['course_enrollment_id']
            : app(ResolveLatestEnrollmentIdAction::class)->execute($studentId);
        if ($enrollmentId === null || $enrollmentId === 0) {
            throw ValidationException::withMessages([
                'course_enrollment_id' => 'Student has no engine enrollment — run halaqa:backfill-structure first.',
            ]);
        }

        $mode = RecitationMode::tryFrom((string) ($data['mode'] ?? RecitationMode::Manual->value));
        if ($mode === null) {
            throw ValidationException::withMessages(['mode' => 'Invalid recitation mode.']);
        }

        $surahId = isset($data['surah_id']) && $data['surah_id'] !== '' ? (int) $data['surah_id'] : null;
        if ($surahId !== null && app(QuranReferenceReader::class)->findSurah($surahId) === null) {
            throw ValidationException::withMessages(['surah_id' => 'Unknown surah.']);
        }

        // §52.19: a submission may answer an assignment; submitting moves it on.
        $assignment = null;
        if (! empty($data['quran_hifz_assignment_id'])) {
            $assignment = QuranHifzAssignment::query()->find((int) $data['quran_hifz_assignment_id']);
            if ($assignment === null || (int) $assignment->student_id !== $studentId) {
                throw ValidationException::withMessages([
                    'quran_hifz_assignment_id' => 'Assignment not found for this student.',
                ]);
            }
        }

        $submission = QuranRecitationSubmission::query()->create([
            'course_enrollment_id' => $enrollmentId,
            'quran_hifz_assignment_id' => $assignment?->id,
            'student_id' => $studentId,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'surah_id' => $surahId,
            'start_ayah_number' => $data['start_ayah_number'] ?? null,
            'end_ayah_number' => $data['end_ayah_number'] ?? null,
            'audio_media_file_id' => $data['audio_media_file_id'] ?? null,
            'mode' => $mode,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'submitted_at' => now(),
            'status' => RecitationSubmissionStatus::Submitted,
        ]);

        if ($assignment !== null) {
            $assignment->status = QuranAssignmentStatus::Submitted;
            $assignment->save();
        }

        return $submission;
    }
}
