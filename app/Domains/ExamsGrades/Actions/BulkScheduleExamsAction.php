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

        // One query for every subject named, rather than one per subject.
        $names = DB::table('subjects')
            ->whereIn('id', array_map('intval', $subjectIds))
            ->pluck('name', 'id');

        $created = [];
        foreach ($subjectIds as $subjectId) {
            $subjectName = $names[(int) $subjectId] ?? null;
            if ($subjectName === null) {
                throw ValidationException::withMessages(['subject_ids' => "Unknown subject {$subjectId}."]);
            }

            $created[] = app(SaveExamAction::class)->execute([
                ...$data,
                'subject_id' => (int) $subjectId,
                'name' => $name.' — '.$subjectName,
            ], null, $actorId);
        }

        return $created;
    }
}
