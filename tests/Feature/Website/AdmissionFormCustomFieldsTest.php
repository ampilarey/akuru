<?php

use App\Domains\Admissions\Models\AdmissionApplication;
use App\Domains\Notifications\Notifications\NewAdmissionApplication;
use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // `AdmissionController::store` notifies every `admin`; Spatie throws if
    // the role has never been created, which a fresh test database has not.
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
});

/**
 * S1.2 named three consumers of the custom-fields engine. Until 2026-09-22 the
 * public admission form was not one of them: `show_in_admission_form` drove an
 * admin *preview* and nothing a visitor could see or fill in.
 */
function admissionField(array $overrides = [])
{
    return makeCustomFieldDefinition(array_merge([
        'entity_type' => CustomFieldEntityType::AdmissionApplications->value,
        'key' => 'previous_school',
        'label_en' => 'Previous school',
        'field_type' => CustomFieldType::Text->value,
        'options' => null,
        'show_in_admission_form' => true,
    ], $overrides));
}

it('renders the fields flagged for the admission form, and only those', function () {
    admissionField();
    admissionField(['key' => 'internal_note', 'label_en' => 'Internal note', 'show_in_admission_form' => false]);

    foreach (['/admissions', '/apply'] as $path) {
        $this->withoutLocalizationMiddleware()
            ->get($path)
            ->assertOk()
            ->assertSee('Previous school')
            ->assertSee('data-custom-field="previous_school"', false)
            ->assertDontSee('Internal note');
    }
});

it('stores the values with the application', function () {
    Notification::fake();
    $field = admissionField();

    $this->withoutLocalizationMiddleware()
        ->post('/admissions', [
            'full_name' => 'Aminath Visitor',
            'phone' => '7771234',
            'source' => 'web',
            'values' => [$field->id => 'Iskandhar School'],
        ])
        ->assertRedirect();

    $application = AdmissionApplication::query()->where('full_name', 'Aminath Visitor')->firstOrFail();

    expect(CustomFieldValue::query()
        ->where('entity_type', CustomFieldEntityType::AdmissionApplications->value)
        ->where('entity_id', $application->id)
        ->where('definition_id', $field->id)
        ->first()?->rawValue())->toBe('Iskandhar School');
});

it('refuses a missing required field and creates nothing — not even the admin email', function () {
    Notification::fake();
    $field = admissionField(['required' => true]);

    $this->withoutLocalizationMiddleware()
        ->from('/admissions')
        ->post('/admissions', [
            'full_name' => 'Nobody Yet',
            'phone' => '7771234',
            'source' => 'web',
            'values' => [$field->id => ''],
        ])
        ->assertRedirect('/admissions')
        ->assertSessionHasErrors('field_'.$field->id);

    // The two writes share a transaction: no orphan application, and no
    // notification about one.
    expect(AdmissionApplication::query()->where('full_name', 'Nobody Yet')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('validates a select against its options on the apply alias too', function () {
    Notification::fake();
    // Somebody to notify, so the "sent once" at the end is a real count.
    \App\Domains\Identity\Models\User::factory()->create()->assignRole('admin');
    $field = admissionField([
        'key' => 'heard_from',
        'label_en' => 'Heard from',
        'field_type' => CustomFieldType::Select->value,
        'options' => [['value' => 'friend', 'label' => 'A friend'], ['value' => 'mosque', 'label' => 'The mosque']],
    ]);

    $this->withoutLocalizationMiddleware()
        ->from('/apply')
        ->post('/apply', [
            'full_name' => 'Hassan Visitor',
            'phone' => '7771234',
            'values' => [$field->id => 'billboard'],
        ])
        ->assertRedirect('/apply')
        ->assertSessionHasErrors('field_'.$field->id);

    $this->withoutLocalizationMiddleware()
        ->post('/apply', [
            'full_name' => 'Hassan Visitor',
            'phone' => '7771234',
            'values' => [$field->id => 'mosque'],
        ])
        ->assertRedirect();

    $application = AdmissionApplication::query()->where('full_name', 'Hassan Visitor')->firstOrFail();
    expect(CustomFieldValue::query()->where('entity_id', $application->id)->first()?->rawValue())->toBe('mosque');
    Notification::assertSentTimes(NewAdmissionApplication::class, 1);
});
