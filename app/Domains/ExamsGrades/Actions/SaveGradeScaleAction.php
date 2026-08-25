<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\GradeScaleType;
use App\Domains\ExamsGrades\Models\GradeScale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveGradeScaleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?GradeScale $scale = null): GradeScale
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $type = GradeScaleType::from((string) $data['type']);
        $bands = $this->bands($data['bands'] ?? []);
        $isDefault = (bool) ($data['is_default'] ?? false);

        return DB::transaction(function () use ($name, $type, $bands, $isDefault, $data, $scale): GradeScale {
            if ($isDefault) {
                GradeScale::query()
                    ->when($scale !== null, fn ($query) => $query->where('id', '!=', $scale->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $payload = [
                'name' => $name,
                'type' => $type,
                'bands' => $bands,
                'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
                'is_default' => $isDefault,
            ];

            if ($scale === null) {
                return GradeScale::query()->create($payload);
            }

            $scale->fill($payload);
            $scale->save();

            return $scale->refresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bands(mixed $bands): array
    {
        if (is_string($bands)) {
            $decoded = json_decode($bands, true);
            $bands = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($bands) || $bands === []) {
            throw ValidationException::withMessages(['bands' => 'At least one band is required.']);
        }

        $normalized = [];
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }

            $grade = trim((string) ($band['grade'] ?? $band['level'] ?? ''));
            if ($grade === '') {
                throw ValidationException::withMessages(['bands' => 'Each band needs a grade or level.']);
            }

            $normalized[] = [
                'min' => isset($band['min']) ? (float) $band['min'] : null,
                'grade' => $grade,
                'point' => isset($band['point']) && $band['point'] !== '' ? (float) $band['point'] : null,
                'descriptor_en' => $this->nullable($band['descriptor_en'] ?? null),
                'descriptor_dv' => $this->nullable($band['descriptor_dv'] ?? null),
                'descriptor_ar' => $this->nullable($band['descriptor_ar'] ?? null),
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['bands' => 'At least one band is required.']);
        }

        return $normalized;
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
