<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\StudentWork;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Photograph a piece of paper work and route it to a pupil.
 *
 * No AI reads the handwritten name (rule 8, and the plan's own judgement that
 * *"photo + pick the pupil"* is most of the value at a fraction of the cost).
 * **Picking the pupil is therefore the only thing standing between a photo and
 * the wrong parent**, which is why it is required rather than defaulted, and
 * why `ReassignStudentWorkAction` exists as a first-class verb rather than an
 * edit form.
 */
class SaveStudentWorkAction
{
    /** A photograph of paper, and nothing else. */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $userId, UploadedFile $photo): StudentWork
    {
        $studentId = (int) ($data['student_id'] ?? 0);

        if ($studentId <= 0) {
            throw ValidationException::withMessages([
                'student_id' => 'Choose whose work this is. Nothing is sent to a family until you do.',
            ]);
        }

        $yearId = (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            throw ValidationException::withMessages([
                'student_id' => 'No academic year is active, so there is nothing to file this against.',
            ]);
        }

        $stored = app(StorePrivateMediaAction::class)->execute($photo, $userId, self::ALLOWED_MIMES);

        if (($stored['id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'photo' => 'That photo could not be saved. A JPEG, PNG, WebP or HEIC, please.',
            ]);
        }

        return StudentWork::query()->create([
            'academic_year_id' => $yearId,
            'student_id' => $studentId,
            'photo_media_id' => (int) $stored['id'],
            'uploaded_by' => $userId,
            'title' => $this->nullable($data['title'] ?? null),
            'note' => $this->nullable($data['note'] ?? null),
            'done_on' => $this->doneOn($data['done_on'] ?? null),
        ]);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function doneOn(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? now()->toDateString() : $value;
    }
}
