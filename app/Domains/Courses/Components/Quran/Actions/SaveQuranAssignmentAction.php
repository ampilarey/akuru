<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentStatus;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentType;
use App\Domains\Courses\Components\Quran\Models\QuranHifzAssignment;
use App\Support\Contracts\QuranReferenceReader;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §52.18 — create or update an assignment. Surahs validate through the
 * reference contract; letter/haraka ids stay bare integers (DB FKs enforce
 * existence — no Arabic component code reference, rule 3).
 */
class SaveQuranAssignmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?QuranHifzAssignment $assignment = null): QuranHifzAssignment
    {
        $type = QuranAssignmentType::tryFrom((string) ($data['assignment_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['assignment_type' => 'Invalid assignment type.']);
        }

        $status = QuranAssignmentStatus::tryFrom(
            (string) ($data['status'] ?? QuranAssignmentStatus::Assigned->value)
        );
        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Invalid assignment status.']);
        }

        $surahId = isset($data['surah_id']) && $data['surah_id'] !== '' ? (int) $data['surah_id'] : null;
        if ($surahId !== null && app(QuranReferenceReader::class)->findSurah($surahId) === null) {
            throw ValidationException::withMessages(['surah_id' => 'Unknown surah.']);
        }

        $payload = [
            'student_id' => (int) $data['student_id'],
            'teacher_id' => (int) $data['teacher_id'],
            'course_id' => $data['course_id'] ?? null,
            'course_offering_id' => $data['course_offering_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'surah_id' => $surahId,
            'start_ayah_number' => $data['start_ayah_number'] ?? null,
            'end_ayah_number' => $data['end_ayah_number'] ?? null,
            'expected_letter_id' => $data['expected_letter_id'] ?? null,
            'expected_haraka_id' => $data['expected_haraka_id'] ?? null,
            'assignment_type' => $type,
            'due_date' => $data['due_date'] ?? null,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ];

        if ($assignment === null) {
            $payload['created_by'] = $data['created_by'] ?? null;

            return QuranHifzAssignment::query()->create($payload);
        }

        $assignment->fill($payload);
        $assignment->save();

        return $assignment->refresh();
    }
}
