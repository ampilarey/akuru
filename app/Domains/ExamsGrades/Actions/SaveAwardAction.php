<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\AwardLevel;
use App\Domains\ExamsGrades\Models\Award;
use Illuminate\Validation\ValidationException;

class SaveAwardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Award $award = null): Award
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $payload = [
            'title' => $title,
            'title_arabic' => $this->nullable($data['title_arabic'] ?? null),
            'title_dhivehi' => $this->nullable($data['title_dhivehi'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'level' => AwardLevel::from((string) ($data['level'] ?? AwardLevel::School->value)),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($award === null) {
            return Award::query()->create($payload);
        }

        $award->fill($payload);
        $award->save();

        return $award->refresh();
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
