<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzProgram;

class ListHifzProgramsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        return HifzProgram::query()
            ->orderBy('name')
            ->get()
            ->map(fn (HifzProgram $program): array => $this->toArray($program))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $program = HifzProgram::query()->find($id);

        return $program ? $this->toArray($program) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(HifzProgram $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
            'status' => $program->status?->value ?? $program->status,
            'academic_year_id' => $program->academic_year_id ? (int) $program->academic_year_id : null,
        ];
    }
}
