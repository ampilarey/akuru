<?php

namespace App\Domains\People\Http\Controllers;

use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldDefinitionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('custom_fields.manage'), 403);

        $entityType = $request->string('entity_type')->toString() ?: CustomFieldEntityType::Students->value;

        $definitions = CustomFieldDefinition::query()
            ->forEntity($entityType)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomFieldDefinition $definition) => $this->serialize($definition));

        return Inertia::render('People/CustomFields/Index', [
            'entityType' => $entityType,
            'entityTypes' => array_map(fn (CustomFieldEntityType $type) => $type->value, CustomFieldEntityType::cases()),
            'fieldTypes' => array_map(fn (CustomFieldType $type) => $type->value, CustomFieldType::cases()),
            'definitions' => $definitions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('custom_fields.manage'), 403);

        $data = $this->validated($request);

        CustomFieldDefinition::query()->create($data);

        return redirect()
            ->route('people.custom-fields.index', ['entity_type' => $data['entity_type']])
            ->with('success', 'Custom field created.');
    }

    public function update(Request $request, CustomFieldDefinition $definition): RedirectResponse
    {
        abort_unless($request->user()?->can('custom_fields.manage'), 403);

        $data = $this->validated($request, $definition->id);

        $definition->update($data);

        return redirect()
            ->route('people.custom-fields.index', ['entity_type' => $data['entity_type']])
            ->with('success', 'Custom field updated.');
    }

    public function destroy(Request $request, CustomFieldDefinition $definition): RedirectResponse
    {
        abort_unless($request->user()?->can('custom_fields.manage'), 403);

        $entityType = $definition->entity_type->value;
        $definition->delete();

        return redirect()
            ->route('people.custom-fields.index', ['entity_type' => $entityType])
            ->with('success', 'Custom field archived.');
    }

    public function admissionPreview(Request $request): Response
    {
        abort_unless($request->user()?->can('custom_fields.manage'), 403);

        $fields = CustomFieldDefinition::query()
            ->forEntity(CustomFieldEntityType::AdmissionApplications)
            ->forAdmissionForm()
            ->get()
            ->map(fn (CustomFieldDefinition $definition) => $this->serialize($definition));

        return Inertia::render('People/CustomFields/AdmissionPreview', [
            'fields' => $fields,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:'.implode(',', array_column(CustomFieldEntityType::cases(), 'value'))],
            'key' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('custom_field_definitions', 'key')
                    ->where(fn ($query) => $query->where('entity_type', $request->input('entity_type')))
                    ->ignore($ignoreId),
            ],
            'label_en' => ['required', 'string', 'max:255'],
            'label_dv' => ['nullable', 'string', 'max:255'],
            'label_ar' => ['nullable', 'string', 'max:255'],
            'field_type' => ['required', 'in:'.implode(',', array_column(CustomFieldType::cases(), 'value'))],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable'],
            'required' => ['sometimes', 'boolean'],
            'show_in_profile' => ['sometimes', 'boolean'],
            'show_in_admission_form' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $data['required'] = (bool) ($data['required'] ?? false);
        $data['show_in_profile'] = (bool) ($data['show_in_profile'] ?? true);
        $data['show_in_admission_form'] = (bool) ($data['show_in_admission_form'] ?? false);
        $data['active'] = (bool) ($data['active'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['options'] = $this->normalizeOptions($data['options'] ?? []);

        return $data;
    }

    /**
     * @param  array<int, mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(function ($option) {
                if (is_array($option)) {
                    $value = (string) ($option['value'] ?? $option['label'] ?? '');

                    return ['value' => $value, 'label' => (string) ($option['label'] ?? $value)];
                }

                $value = trim((string) $option);

                return ['value' => $value, 'label' => $value];
            })
            ->filter(fn (array $option) => $option['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomFieldDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'entity_type' => $definition->entity_type->value,
            'key' => $definition->key,
            'label_en' => $definition->label_en,
            'label_dv' => $definition->label_dv,
            'label_ar' => $definition->label_ar,
            'label' => $definition->localizedLabel(),
            'field_type' => $definition->field_type->value,
            'options' => $definition->options ?? [],
            'required' => $definition->required,
            'show_in_profile' => $definition->show_in_profile,
            'show_in_admission_form' => $definition->show_in_admission_form,
            'sort_order' => $definition->sort_order,
            'active' => $definition->active,
        ];
    }
}
