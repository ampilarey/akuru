<?php

namespace App\Domains\People\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\People\Enums\SensitiveNoteCategory;
use App\Domains\People\Models\StudentSensitiveNote;
use Illuminate\Validation\ValidationException;

/**
 * Record a health or welfare note about a pupil.
 *
 * Editing is deliberately narrow: only the author may change their own note,
 * and only while it is in use. A welfare note is somebody's professional
 * observation with their name on it, and a second member of staff rewriting it
 * would leave a record that says one thing and a signature that says another.
 * Anyone else who disagrees adds their own note; the disagreement is then part
 * of the record, which is what a later reader needs to see.
 */
class SaveSensitiveNoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $authorId, ?StudentSensitiveNote $note = null): StudentSensitiveNote
    {
        $summary = trim((string) ($data['summary'] ?? ''));

        if ($summary === '') {
            throw ValidationException::withMessages([
                'summary' => __('people.error_note_summary'),
            ]);
        }

        $category = SensitiveNoteCategory::tryFrom((string) ($data['category'] ?? ''))
            ?? SensitiveNoteCategory::Other;

        $attributes = [
            'category' => $category->value,
            'summary' => $summary,
            'body' => $this->nullable($data['body'] ?? null),
            'review_on' => $this->nullable($data['review_on'] ?? null),
        ];

        if ($note !== null) {
            if ((int) $note->author_id !== $authorId) {
                throw ValidationException::withMessages([
                    'summary' => __('people.error_note_author'),
                ]);
            }

            if ($note->archived_at !== null) {
                throw ValidationException::withMessages([
                    'summary' => __('people.error_note_archived'),
                ]);
            }

            $note->update($attributes);

            return $note->refresh();
        }

        $studentId = (int) ($data['student_id'] ?? 0);

        if ($studentId <= 0) {
            throw ValidationException::withMessages(['student_id' => __('people.error_note_student')]);
        }

        // Rule 3: People may not read Academics' model, so the year comes
        // through the seam HR and Forms already use.
        $yearId = (int) (app(ResolveAcademicYearForDateAction::class)->execute()['id'] ?? 0);

        if ($yearId === 0) {
            throw ValidationException::withMessages([
                'summary' => __('people.error_note_no_year'),
            ]);
        }

        return StudentSensitiveNote::query()->create($attributes + [
            'academic_year_id' => $yearId,
            'student_id' => $studentId,
            'author_id' => $authorId,
        ]);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
