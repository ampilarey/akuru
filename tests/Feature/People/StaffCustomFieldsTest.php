<?php

use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * S1.2's third consumer. `CustomFieldEntityType::Staff` existed from the day
 * the engine shipped; no screen read or wrote it until 2026-09-22.
 */
function staffField(array $overrides = [])
{
    return makeCustomFieldDefinition(array_merge([
        'entity_type' => CustomFieldEntityType::Staff->value,
        'key' => 'teaching_licence',
        'label_en' => 'Teaching licence no.',
        'field_type' => CustomFieldType::Text->value,
        'options' => null,
        'show_in_profile' => true,
    ], $overrides));
}

it('shows the staff profile its own custom fields, with values', function () {
    $staff = makeStaffProfile();
    $field = staffField();
    staffField(['key' => 'hidden_one', 'label_en' => 'Hidden', 'show_in_profile' => false]);
    CustomFieldValue::query()->create([
        'definition_id' => $field->id,
        'entity_type' => CustomFieldEntityType::Staff->value,
        'entity_id' => $staff->id,
        'value' => 'TL-2026-001',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->get('/people/staff/'.$staff->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Staff/Show')
            ->has('customFields', 1)
            ->where('customFields.0.key', 'teaching_licence')
            ->where('customFields.0.value', 'TL-2026-001'));
});

it('saves staff custom fields through the one engine, and validates them', function () {
    $staff = makeStaffProfile();
    $field = staffField(['required' => true]);

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->put('/people/staff/'.$staff->id.'/custom-fields', ['values' => [$field->id => '']])
        ->assertSessionHasErrors('field_'.$field->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->put('/people/staff/'.$staff->id.'/custom-fields', ['values' => [$field->id => 'TL-2026-002']])
        ->assertRedirect('/people/staff/'.$staff->id);

    expect(CustomFieldValue::query()
        ->where('entity_type', CustomFieldEntityType::Staff->value)
        ->where('entity_id', $staff->id)
        ->first()?->rawValue())->toBe('TL-2026-002');
});

it('keeps a student definition off the staff profile', function () {
    $staff = makeStaffProfile();
    makeCustomFieldDefinition(); // blood_group, entity `students`

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->get('/people/staff/'.$staff->id)
        ->assertInertia(fn (Assert $page) => $page->has('customFields', 0));
});
