<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Domains\People\Models\CustomFieldValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveCustomFieldValuesAction
{
    /**
     * @param  array<int|string, mixed>  $values  keyed by definition id
     *
     * @throws ValidationException
     */
    public function execute(CustomFieldEntityType|string $entityType, int $entityId, array $values): void
    {
        $entityType = $entityType instanceof CustomFieldEntityType
            ? $entityType
            : CustomFieldEntityType::from($entityType);

        $definitions = CustomFieldDefinition::query()
            ->forEntity($entityType)
            ->active()
            ->get()
            ->keyBy('id');

        $normalized = [];
        $rules = [];
        $messages = [];

        foreach ($definitions as $definition) {
            $raw = $values[$definition->id] ?? $values[(string) $definition->id] ?? null;
            $normalized[$definition->id] = $this->normalizeIncoming($definition->field_type, $raw);
            $key = 'field_'.$definition->id;

            $rules[$key] = $this->rulesFor($definition);
            $messages[$key.'.required'] = $definition->localizedLabel().' is required.';
            $messages[$key.'.in'] = $definition->localizedLabel().' must be one of the allowed options.';
            $messages[$key.'.min'] = $definition->localizedLabel().' is required.';

            if ($definition->field_type === CustomFieldType::Multiselect) {
                $options = $definition->optionValues();
                if ($options !== []) {
                    $rules[$key.'.*'] = ['string', 'in:'.implode(',', $options)];
                    $messages[$key.'.*.in'] = $definition->localizedLabel().' must be one of the allowed options.';
                }
            }
        }

        $toValidate = [];
        foreach ($normalized as $id => $value) {
            $toValidate['field_'.$id] = $value;
        }

        $validator = Validator::make($toValidate, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        foreach ($definitions as $definition) {
            $value = $normalized[$definition->id];

            if ($this->isEmpty($definition->field_type, $value)) {
                CustomFieldValue::query()
                    ->where('definition_id', $definition->id)
                    ->where('entity_type', $entityType->value)
                    ->where('entity_id', $entityId)
                    ->delete();

                continue;
            }

            CustomFieldValue::query()->updateOrCreate(
                [
                    'definition_id' => $definition->id,
                    'entity_type' => $entityType->value,
                    'entity_id' => $entityId,
                ],
                [
                    'value' => ['v' => $value],
                ],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function rulesFor(CustomFieldDefinition $definition): array
    {
        $rules = [];

        if ($definition->required) {
            $rules[] = $definition->field_type === CustomFieldType::Multiselect
                ? 'required'
                : 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules = array_merge($rules, match ($definition->field_type) {
            CustomFieldType::Text, CustomFieldType::Textarea => ['string'],
            CustomFieldType::Number => ['numeric'],
            CustomFieldType::Date => ['date'],
            CustomFieldType::Boolean => ['boolean'],
            CustomFieldType::Select => ['string'],
            CustomFieldType::Multiselect => ['array'],
        });

        $options = $definition->optionValues();

        if ($definition->field_type === CustomFieldType::Select && $options !== []) {
            $rules[] = 'in:'.implode(',', $options);
        }

        if ($definition->required && $definition->field_type === CustomFieldType::Multiselect) {
            $rules[] = 'min:1';
        }

        return $rules;
    }

    private function normalizeIncoming(CustomFieldType $type, mixed $raw): mixed
    {
        if ($type === CustomFieldType::Multiselect) {
            if ($raw === null || $raw === '') {
                return [];
            }

            return array_values(is_array($raw) ? $raw : [$raw]);
        }

        if ($raw === '' || $raw === null) {
            return $type === CustomFieldType::Boolean ? false : null;
        }

        return match ($type) {
            CustomFieldType::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $raw,
            CustomFieldType::Number => is_numeric($raw) ? $raw + 0 : $raw,
            default => $raw,
        };
    }

    private function isEmpty(CustomFieldType $type, mixed $value): bool
    {
        return match ($type) {
            CustomFieldType::Boolean => false,
            CustomFieldType::Multiselect => $value === [] || $value === null,
            default => $value === null || $value === '',
        };
    }
}
