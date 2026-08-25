<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Award;
use App\Domains\ExamsGrades\Models\StudentAward;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueStudentAwardsAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, StudentAward>
     */
    public function execute(array $data, ?int $actorId = null): Collection
    {
        $award = Award::query()->where('active', true)->findOrFail((int) $data['award_id']);
        $studentIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => (int) $id,
            (array) ($data['student_ids'] ?? []),
        ), fn (int $id) => $id > 0)));

        if ($studentIds === []) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one student.']);
        }

        $yearId = (int) $data['academic_year_id'];
        $termId = isset($data['term_id']) && $data['term_id'] !== '' && $data['term_id'] !== null
            ? (int) $data['term_id']
            : null;
        $date = (string) ($data['awarded_date'] ?? now()->toDateString());
        $notes = trim((string) ($data['notes'] ?? '')) ?: null;

        $issued = collect();
        foreach ($studentIds as $studentId) {
            $row = StudentAward::query()->create([
                'student_id' => $studentId,
                'award_id' => $award->id,
                'academic_year_id' => $yearId,
                'term_id' => $termId,
                'awarded_date' => $date,
                'notes' => $notes,
            ]);

            $student = DB::table('students')->where('id', $studentId)->first();
            $html = app(DocumentRendererInterface::class)->render('award-certificate', [
                'locale' => 'en',
                'dir' => 'ltr',
                'award' => [
                    'title' => $award->title,
                    'description' => $award->description,
                    'level' => $award->level->value,
                ],
                'student' => [
                    'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                    'number' => $student->student_id ?? null,
                ],
                'awarded_date' => $date,
                'notes' => $notes,
            ]);
            $document = app(StoreGeneratedDocumentAction::class)->execute(
                $row->getMorphClass(),
                $row->id,
                'award_certificate',
                sprintf('Certificate — %s', $award->title),
                $html,
                'html',
                $actorId,
            );
            $row->update(['certificate_document_id' => $document->id]);
            $issued->push($row->fresh());
        }

        return $issued->values();
    }
}
