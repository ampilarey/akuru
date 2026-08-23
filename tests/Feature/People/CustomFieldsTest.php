<?php

use App\Domains\People\Actions\SaveCustomFieldValuesAction;
use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Domains\People\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('validates custom field values per type', function () {
    $student = makeStudent();
    $text = makeCustomFieldDefinition([
        'key' => 'nickname',
        'label_en' => 'Nickname',
        'field_type' => CustomFieldType::Text->value,
        'options' => null,
    ]);
    $number = makeCustomFieldDefinition([
        'key' => 'siblings',
        'label_en' => 'Siblings',
        'field_type' => CustomFieldType::Number->value,
        'options' => null,
    ]);
    $date = makeCustomFieldDefinition([
        'key' => 'joined_club',
        'label_en' => 'Joined club',
        'field_type' => CustomFieldType::Date->value,
        'options' => null,
    ]);
    $select = makeCustomFieldDefinition(['key' => 'blood_group']);
    $multi = makeCustomFieldDefinition([
        'key' => 'languages',
        'label_en' => 'Languages',
        'field_type' => CustomFieldType::Multiselect->value,
        'options' => [
            ['value' => 'dv', 'label' => 'Dhivehi'],
            ['value' => 'en', 'label' => 'English'],
        ],
    ]);
    $bool = makeCustomFieldDefinition([
        'key' => 'needs_transport',
        'label_en' => 'Needs transport',
        'field_type' => CustomFieldType::Boolean->value,
        'options' => null,
    ]);

    expect(fn () => app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [
            $number->id => 'not-a-number',
        ],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [
            $select->id => 'ZZ',
        ],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [
            $date->id => 'not-a-date',
        ],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [
            $multi->id => ['fr'],
        ],
    ))->toThrow(ValidationException::class);

    app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [
            $text->id => 'Mimi',
            $number->id => 2,
            $date->id => '2026-01-15',
            $select->id => 'A+',
            $multi->id => ['dv', 'en'],
            $bool->id => true,
        ],
    );

    expect(CustomFieldValue::query()->where('entity_id', $student->id)->count())->toBe(6)
        ->and(CustomFieldValue::query()->where('definition_id', $select->id)->first()?->rawValue())->toBe('A+')
        ->and(CustomFieldValue::query()->where('definition_id', $multi->id)->first()?->rawValue())->toBe(['dv', 'en'])
        ->and(CustomFieldValue::query()->where('definition_id', $bool->id)->first()?->rawValue())->toBeTrue();
});

it('enforces required custom fields', function () {
    $student = makeStudent();
    $required = makeCustomFieldDefinition([
        'key' => 'house',
        'label_en' => 'House',
        'field_type' => CustomFieldType::Text->value,
        'required' => true,
        'options' => null,
    ]);

    expect(fn () => app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [$required->id => ''],
    ))->toThrow(ValidationException::class);

    app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::Students,
        $student->id,
        [$required->id => 'Red'],
    );

    expect(CustomFieldValue::query()->where('definition_id', $required->id)->first()?->rawValue())->toBe('Red');
});

it('renders admission-form custom fields and soft-deletes definitions without dropping values', function () {
    $admin = actingPeopleAdmin();
    $shown = makeCustomFieldDefinition([
        'entity_type' => CustomFieldEntityType::AdmissionApplications->value,
        'key' => 'previous_school',
        'label_en' => 'Previous school',
        'field_type' => CustomFieldType::Text->value,
        'show_in_admission_form' => true,
        'options' => null,
    ]);
    makeCustomFieldDefinition([
        'entity_type' => CustomFieldEntityType::AdmissionApplications->value,
        'key' => 'internal_note',
        'label_en' => 'Internal note',
        'field_type' => CustomFieldType::Textarea->value,
        'show_in_admission_form' => false,
        'options' => null,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.custom-fields.admission-preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/CustomFields/AdmissionPreview')
            ->has('fields', 1)
            ->where('fields.0.key', 'previous_school')
        );

    app(SaveCustomFieldValuesAction::class)->execute(
        CustomFieldEntityType::AdmissionApplications,
        99,
        [$shown->id => 'Iskandhar School'],
    );

    $shown->delete();

    expect(CustomFieldDefinition::query()->find($shown->id))->toBeNull()
        ->and(CustomFieldDefinition::withTrashed()->find($shown->id))->not->toBeNull()
        ->and(CustomFieldValue::query()->where('definition_id', $shown->id)->first()?->rawValue())->toBe('Iskandhar School');
});

it('lets admins manage custom field definitions and save student values from the profile', function () {
    $admin = actingPeopleAdmin();
    $student = makeStudent();
    $field = makeCustomFieldDefinition([
        'key' => 'nickname',
        'label_en' => 'Nickname',
        'field_type' => CustomFieldType::Text->value,
        'options' => null,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.custom-fields.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('People/CustomFields/Index'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('People/Students/Index'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('people.students.custom-fields.update', $student), [
            'values' => [$field->id => 'Mimi'],
        ])
        ->assertRedirect();

    expect(CustomFieldValue::query()->where('definition_id', $field->id)->first()?->rawValue())->toBe('Mimi');
});

it('hides medical data without students.view-sensitive', function () {
    $student = makeStudent(['medical_conditions' => 'Asthma']);
    $admin = actingPeopleAdmin(['custom_fields.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.students.show', ['student' => $student, 'tab' => 'medical']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Show')
            ->where('canViewSensitive', false)
            ->where('student.medical', null)
        );
});
