<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use Illuminate\Validation\ValidationException;

class SaveExamTypeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?ExamType $type = null): ExamType
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $code = ExamTypeCode::from((string) $data['code']);

        $duplicate = ExamType::query()
            ->where('code', $code->value)
            ->when($type !== null, fn ($query) => $query->where('id', '!=', $type->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['code' => 'That exam type code already exists.']);
        }

        $payload = [
            'name' => $name,
            'name_arabic' => $this->nullable($data['name_arabic'] ?? null),
            'name_dhivehi' => $this->nullable($data['name_dhivehi'] ?? null),
            'code' => $code,
            'default_weight' => (int) ($data['default_weight'] ?? 0),
            'counts_toward_final' => array_key_exists('counts_toward_final', $data)
                ? (bool) $data['counts_toward_final']
                : true,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($type === null) {
            return ExamType::query()->create($payload);
        }

        $type->fill($payload);
        $type->save();

        return $type->refresh();
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
