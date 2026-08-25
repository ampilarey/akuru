<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use Illuminate\Validation\ValidationException;

class SaveReportCardTemplateAction
{
    /**
     * @param  list<string>  $allowed
     */
    public const SECTIONS = [
        'grades_table',
        'attendance_summary',
        'behavior_summary',
        'competencies',
        'teacher_comment',
        'head_comment',
        'awards',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?ReportCardTemplate $template = null): ReportCardTemplate
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $sections = $this->sections($data['sections'] ?? self::SECTIONS);
        $appliesTo = $this->classIds($data['applies_to'] ?? null);

        $payload = [
            'name' => $name,
            'applies_to' => $appliesTo,
            'sections' => $sections,
            'header' => $this->nullable($data['header'] ?? null),
            'footer' => $this->nullable($data['footer'] ?? null),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($template === null) {
            return ReportCardTemplate::query()->create($payload);
        }

        $template->fill($payload);
        $template->save();

        return $template->refresh();
    }

    /**
     * @return list<string>
     */
    private function sections(mixed $value): array
    {
        $items = is_string($value) ? preg_split('/[,\s]+/', $value) : (array) $value;
        $clean = [];
        foreach ($items as $item) {
            $key = trim((string) $item);
            if ($key === '' || ! in_array($key, self::SECTIONS, true)) {
                continue;
            }
            $clean[] = $key;
        }

        if ($clean === []) {
            throw ValidationException::withMessages(['sections' => 'At least one valid section is required.']);
        }

        return array_values(array_unique($clean));
    }

    /**
     * @return list<int>|null
     */
    private function classIds(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $items = is_string($value) ? preg_split('/[,\s]+/', $value) : (array) $value;

        return array_values(array_unique(array_filter(array_map(
            fn ($id) => (int) $id,
            $items,
        ), fn (int $id) => $id > 0)));
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
