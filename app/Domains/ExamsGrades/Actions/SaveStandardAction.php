<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Standard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveStandardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Standard $standard = null): Standard
    {
        $code = trim((string) ($data['code'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        if ($code === '' || $title === '') {
            throw ValidationException::withMessages(['code' => 'Code and title are required.']);
        }

        $duplicate = Standard::query()
            ->where('code', $code)
            ->when($standard !== null, fn ($query) => $query->where('id', '!=', $standard->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['code' => 'That standard code already exists.']);
        }

        $subjectId = $this->optionalId($data['subject_id'] ?? null);
        if ($subjectId !== null && ! DB::table('subjects')->where('id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Subject not found.']);
        }

        $parentId = $this->optionalId($data['parent_id'] ?? null);
        if ($parentId !== null && ! Standard::query()->where('id', $parentId)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Parent standard not found.']);
        }

        $payload = [
            'subject_id' => $subjectId,
            'code' => $code,
            'title' => $title,
            'title_arabic' => $this->nullable($data['title_arabic'] ?? null),
            'title_dhivehi' => $this->nullable($data['title_dhivehi'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'parent_id' => $parentId,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($standard === null) {
            return Standard::query()->create($payload);
        }

        $standard->fill($payload);
        $standard->save();

        return $standard->refresh();
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
