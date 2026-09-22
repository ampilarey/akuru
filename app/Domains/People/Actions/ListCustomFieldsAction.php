<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Domains\People\Models\CustomFieldValue;

/**
 * The custom-fields engine's read side, in one place (S1.2: "rendered
 * automatically by one component" — which needs one shape to render).
 *
 * Until 2026-09-22 only the student profile consumed the engine; the public
 * admission form and the staff profile, which the spec named as the other two
 * consumers, rendered nothing. Both now read through here.
 */
class ListCustomFieldsAction
{
    /**
     * Definitions flagged for the public admission form, in order, with no
     * values (there is no application yet).
     *
     * @return list<array<string, mixed>>
     */
    public function forAdmissionForm(): array
    {
        return CustomFieldDefinition::query()
            ->forEntity(CustomFieldEntityType::AdmissionApplications)
            ->forAdmissionForm()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $definition): array => $this->serialize($definition))
            ->all();
    }

    /**
     * Definitions flagged for a profile screen, with the entity's current
     * value on each.
     *
     * @return list<array<string, mixed>>
     */
    public function forProfile(CustomFieldEntityType $entityType, int $entityId): array
    {
        $values = CustomFieldValue::query()
            ->where('entity_type', $entityType->value)
            ->where('entity_id', $entityId)
            ->get()
            ->keyBy('definition_id');

        return CustomFieldDefinition::query()
            ->forEntity($entityType)
            ->forProfile()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $definition): array => $this->serialize($definition) + [
                'value' => $values->get($definition->id)?->rawValue(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomFieldDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'key' => $definition->key,
            'label' => $definition->localizedLabel(),
            'field_type' => $definition->field_type->value,
            // Options are stored either as `[{value, label}]` or as bare
            // strings; every consumer gets the object form.
            'options' => collect($definition->options ?? [])
                ->map(fn ($option): array => is_array($option)
                    ? ['value' => (string) ($option['value'] ?? ''), 'label' => (string) ($option['label'] ?? $option['value'] ?? '')]
                    : ['value' => (string) $option, 'label' => (string) $option])
                ->filter(fn (array $option): bool => $option['value'] !== '')
                ->values()
                ->all(),
            'required' => (bool) $definition->required,
        ];
    }
}
