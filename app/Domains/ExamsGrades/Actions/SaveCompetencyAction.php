<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Competency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCompetencyAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Competency $competency = null): Competency
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $subjectId = (int) ($data['subject_id'] ?? 0);
        if ($subjectId < 1 || ! DB::table('subjects')->where('id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Subject is required.']);
        }

        $payload = [
            'subject_id' => $subjectId,
            'name' => $name,
            'name_arabic' => $this->nullable($data['name_arabic'] ?? null),
            'name_dhivehi' => $this->nullable($data['name_dhivehi'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($competency === null) {
            return Competency::query()->create($payload);
        }

        $competency->fill($payload);
        $competency->save();

        return $competency->refresh();
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
