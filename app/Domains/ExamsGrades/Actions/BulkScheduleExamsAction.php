<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Exam;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkScheduleExamsAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<Exam>
     */
    public function execute(array $data, ?int $actorId = null): array
    {
        $subjectIds = $data['subject_ids'] ?? [];
        if (is_string($subjectIds)) {
            $decoded = json_decode($subjectIds, true);
            $subjectIds = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($subjectIds) || $subjectIds === []) {
            throw ValidationException::withMessages(['subject_ids' => 'Pick at least one subject.']);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $created = [];
        foreach ($subjectIds as $subjectId) {
            $subject = DB::table('subjects')->where('id', (int) $subjectId)->first();
            if ($subject === null) {
                throw ValidationException::withMessages(['subject_ids' => "Unknown subject {$subjectId}."]);
            }

            $created[] = app(SaveExamAction::class)->execute([
                ...$data,
                'subject_id' => (int) $subjectId,
                'name' => $name.' — '.$subject->name,
            ], null, $actorId);
        }

        return $created;
    }
}
