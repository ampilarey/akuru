<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Forms\Enums\FormFieldType;
use App\Domains\Forms\Models\Form;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a form.
 *
 * Fields are normalised here rather than trusted from the request: a field with
 * no label, or a select with no options, is unanswerable, and discovering that
 * after it reached every family is too late.
 */
class SaveFormAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $authorId, ?Form $form = null): Form
    {
        $fields = $this->normaliseFields($data['fields'] ?? []);

        if ($fields === []) {
            throw ValidationException::withMessages([
                'fields' => 'A form needs at least one question.',
            ]);
        }

        $attributes = [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => $this->nullableString($data['description'] ?? null),
            'fields' => $fields,
            'target_audience' => $data['target_audience'] ?? null,
            'target_classes' => $data['target_classes'] ?? null,
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'requires_parent_confirmation' => (bool) ($data['requires_parent_confirmation'] ?? false),
            'is_published' => (bool) ($data['is_published'] ?? false),
        ];

        if ($attributes['title'] === '') {
            throw ValidationException::withMessages(['title' => 'A form needs a title.']);
        }

        // An anonymous answer has nobody to confirm for, so the two settings
        // cannot both be true. Refusing here beats shipping forms that quietly
        // never become confirmable.
        if ($attributes['is_anonymous'] && $attributes['requires_parent_confirmation']) {
            throw ValidationException::withMessages([
                'requires_parent_confirmation' => 'An anonymous form cannot ask a guardian to confirm.',
            ]);
        }

        if ($form !== null) {
            // Questions are frozen once anyone has answered: rewording or
            // reordering them would silently change what past answers meant.
            if ($form->responses()->exists()) {
                unset($attributes['fields'], $attributes['is_anonymous'], $attributes['requires_parent_confirmation']);
            }

            $form->update($attributes);

            return $form->refresh();
        }

        return Form::query()->create([
            ...$attributes,
            'created_by' => $authorId,
            'academic_year_id' => app(ResolveAcademicYearForDateAction::class)
                ->execute(now()->timezone(config('app.timezone'))->toDateString())['id'] ?? null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function normaliseFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $index => $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $type = FormFieldType::tryFrom((string) ($field['type'] ?? 'text')) ?? FormFieldType::Text;

            $options = array_values(array_filter(
                array_map(fn ($o): string => trim((string) $o), $field['options'] ?? []),
                fn (string $o): bool => $o !== '',
            ));

            if ($type->needsOptions() && count($options) < 2) {
                throw ValidationException::withMessages([
                    "fields.{$index}.options" => "“{$label}” needs at least two options.",
                ]);
            }

            $out[] = [
                // A stable key so an answer survives the label being corrected
                // for a typo — the label is display, the key is identity.
                'key' => (string) ($field['key'] ?? 'f'.($index + 1)),
                'label' => $label,
                'type' => $type->value,
                'options' => $type->needsOptions() ? $options : [],
                'required' => (bool) ($field['required'] ?? false),
            ];
        }

        return $out;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
