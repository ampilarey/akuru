<?php

use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\People\Actions\SaveEmergencyContactAction;
use App\Domains\People\Enums\ConsentSource;
use App\Domains\People\Enums\ConsentType;
use App\Domains\People\Enums\GuardianConsentStatus;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\GuardianVerificationStatus;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Consent;
use App\Domains\People\Models\EmergencyContact;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The school office's people screens in Dhivehi and Arabic (BACKLOG C21,
 * slice PE1, STATUS §5qp).
 *
 * The students list and a student's profile read no phrase book. Every word
 * on them was English; a pupil's status, a gender, a guardian's relationship,
 * a link's consent and verification, a consent's type and source and a
 * behaviour record's type and category were printed as codes (*prospective*,
 * *photo_media_use*, *sms_keyword*); a guardian's responsibilities read
 * *primary pickup financial*; the list printed a class without its section;
 * and so was everything the server said — ten saved messages and fourteen
 * refusals, the reasons it wrote in a pupil's status history among them.
 *
 * The defects that went with them: every guardian on file was offered to
 * attach, and attaching one already linked was a 500; a refused attach, a
 * refused Save or Detach on a guardian, a refused contact removal and a
 * refused consent were said nowhere, and the contact form only its name's and
 * phone's; the list could not be read for a class, though the server took
 * one; and recording a consent the pupil already had said it was recorded.
 */
uses(RefreshDatabase::class);

/** The student screens; every phrase on them is `t.key || 'English'`, from the `people` book. */
function peopleScreens(): array
{
    return ['People/Students/Index', 'People/Students/Show'];
}

/** Where the server writes what those screens say. */
function peopleServerFiles(): array
{
    return [
        'app/Domains/People/Http/Controllers/StudentDirectoryController.php',
        'app/Domains/People/Http/Controllers/StudentConsentController.php',
        'app/Domains/People/Actions/SaveStudentAction.php',
        'app/Domains/People/Actions/SaveEmergencyContactAction.php',
        'app/Domains/People/Actions/RemoveEmergencyContactAction.php',
        'app/Domains/People/Actions/RecordGuardianLinkPolicyAction.php',
        'app/Domains/People/Actions/AttachGuardianAction.php',
        'app/Domains/People/Actions/SaveCustomFieldValuesAction.php',
    ];
}

function peopleBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/people.php");
}

it('keys every string on the student screens in three languages', function () {
    [$en, $dv, $ar] = [peopleBook('en'), peopleBook('dv'), peopleBook('ar')];

    foreach (peopleScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: people.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: people.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: people.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: people.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "people.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "people.{$key} is English in Arabic");
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }

    // The profile's tabs read from the book too, by their own keys.
    $show = file_get_contents(resource_path('js/Pages/People/Students/Show.jsx'));
    preg_match_all("/key: '([a-z_]+)', label: '([^']+)'/", $show, $tabs, PREG_SET_ORDER);
    expect($tabs)->toHaveCount(8);
    foreach ($tabs as [, $key, $label]) {
        expect($en[$key] ?? null)->toBe($label)
            ->and($dv[$key] ?? null)->toMatch('/\p{Thaana}/u')
            ->and($ar[$key] ?? null)->toMatch('/\p{Arabic}/u');
    }
});

it('names every field the student screens post, so a refusal by Laravel’s own rules reads whole in Dhivehi and Arabic', function () {
    $dhivehi = (require resource_path('lang/dv/validation.php'))['attributes'];
    $arabic = (require resource_path('lang/ar/validation.php'))['attributes'];

    $fields = [];
    foreach (array_filter(peopleServerFiles(), fn (string $file) => str_contains($file, '/Controllers/')) as $file) {
        preg_match_all("/'([a-z_]+(?:\\.\\*(?:\\.[a-z_]+)?)?)' => \\[(?=[^\\]]*'(?:required|nullable|sometimes|integer|string|array|boolean|date|numeric)')/", file_get_contents(base_path($file)), $found);
        $fields = [...$fields, ...$found[1]];
    }
    expect($fields)->toContain('date_of_birth', 'guardian_relationship', 'admission_date', 'priority', 'consent_type', 'granted', 'verification_status');

    foreach (array_unique($fields) as $field) {
        expect(array_key_exists($field, $dhivehi))->toBeTrue("{$field} has no Dhivehi name")
            ->and(array_key_exists($field, $arabic))->toBeTrue("{$field} has no Arabic name");
    }
});

it('names every code the student screens show, in all three languages', function () {
    $codes = [
        ...array_map(fn ($case) => 'student_status_'.$case->value, StudentStatus::cases()),
        ...array_map(fn ($case) => 'relationship_'.$case->value, GuardianRelationship::cases()),
        ...array_map(fn ($case) => 'consent_status_'.$case->value, GuardianConsentStatus::cases()),
        ...array_map(fn ($case) => 'verification_status_'.$case->value, GuardianVerificationStatus::cases()),
        ...array_map(fn ($case) => 'consent_type_'.$case->value, ConsentType::cases()),
        ...array_map(fn ($case) => 'consent_source_'.$case->value, ConsentSource::cases()),
        ...array_map(fn ($case) => 'behavior_type_'.$case->value, BehaviorType::cases()),
        // The three behaviour categories a school starts with (OA3).
        'behavior_category_conduct', 'behavior_category_homework', 'behavior_category_other',
        'gender_female', 'gender_male',
        // A reason the system writes in a pupil's status history.
        'history_reason_created', 'history_reason_changed', 'history_reason_promotion_leave', 'history_reason_promotion_graduate',
    ];

    foreach ($codes as $key) {
        expect(trans("people.{$key}", [], 'en'))->not->toBe("people.{$key}", "people.{$key} has no English")
            ->and(trans("people.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "people.{$key} in Dhivehi")
            ->and(trans("people.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "people.{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the student screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (peopleServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(peopleServerFiles());
    expect($keys)->toContain('people.flash_student_created', 'people.flash_consent_unchanged', 'people.error_guardian_already_linked',
        'people.error_contact_other_student', 'people.error_verification_status', 'people.error_field_required', 'people.student_number');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('serves the student screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'B');
    $office = actingPeopleAdmin();
    $guardian = makeGuardian();
    $dv = peopleBook('dv');

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('People/Students/Index')->where('t.students_title', $dv['students_title']));

    // A student without a first name is refused, the field named in Dhivehi;
    // one with it is added, said in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.store'), ['last_name' => 'Qasim', 'date_of_birth' => '2013-04-04', 'gender' => 'female', 'status' => 'active'])
        ->assertSessionHasErrors('first_name');
    expect(session('errors')->first('first_name'))->toMatch('/\p{Thaana}/u')->not->toMatch('/[A-Za-z]/');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.store'), ['first_name' => 'Zunaira', 'last_name' => 'Qasim', 'date_of_birth' => '2013-04-04', 'gender' => 'female', 'status' => 'active', 'class_id' => $class->id, 'student_id' => 'PE1-01'])
        ->assertSessionHas('success', $dv['flash_student_created']);
    $student = Student::query()->where('student_id', 'PE1-01')->sole();

    // A number another pupil has is refused, named as the student number —
    // the app's `student_id` is a pupil everywhere else.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.store'), ['first_name' => 'Iyas', 'last_name' => 'Didi', 'date_of_birth' => '2014-09-09', 'gender' => 'male', 'status' => 'active', 'student_id' => 'PE1-01'])
        ->assertSessionHasErrors('student_id');
    expect(session('errors')->first('student_id'))->toContain($dv['student_number']);

    // The list reads for a class, the class with its section.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.index', ['class_id' => $class->id]))
        ->assertInertia(fn (Assert $page) => $page->has('students', 1)->where('students.0.class_section', 'B'));

    // The profile serves in Dhivehi; the reason the directory wrote in the
    // status history is named.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.show', $student))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('People/Students/Show')
            ->where('t.tab_overview', $dv['tab_overview'])
            ->where('statusHistory.0.reason_key', 'history_reason_created')
            ->where('availableGuardians.0.id', $guardian->id));

    // A guardian is attached, said in Dhivehi; no longer offered; attached
    // again, refused in Dhivehi — it was a 500.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.guardians.attach', $student), ['guardian_id' => $guardian->id, 'relationship' => 'mother'])
        ->assertSessionHas('success', $dv['flash_guardian_attached']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.show', ['student' => $student, 'tab' => 'guardians']))
        ->assertInertia(fn (Assert $page) => $page->has('availableGuardians', 0)->has('guardians', 1));
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.guardians.attach', $student), ['guardian_id' => $guardian->id, 'relationship' => 'mother'])
        ->assertSessionHasErrors(['guardian_id' => $dv['error_guardian_already_linked']]);

    // A guardian's record for a pupil it is not linked to is refused in Dhivehi.
    $other = makeStudent();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('people.students.guardians.policy', ['student' => $other, 'guardian' => $guardian]), ['verification_status' => 'verified'])
        ->assertSessionHasErrors(['guardian' => $dv['error_guardian_not_linked']]);

    // A contact is saved in Dhivehi; one taken off through another pupil's
    // page is refused in Dhivehi, and stays.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.emergency-contacts.store', $student), ['name' => 'Aminath', 'phone' => '7777777', 'priority' => 1])
        ->assertSessionHas('success', $dv['flash_contact_saved']);
    $contact = EmergencyContact::query()->sole();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->delete(route('people.students.emergency-contacts.destroy', ['student' => $other, 'contact' => $contact]))
        ->assertSessionHasErrors(['contact' => $dv['error_contact_other_student']]);
    expect(EmergencyContact::query()->count())->toBe(1);
    expect(fn () => app(SaveEmergencyContactAction::class)->execute((int) $student->id, ['name' => ' ', 'phone' => '7777777']))
        ->toThrow(ValidationException::class, $dv['error_contact_name']);

    // A consent is recorded in Dhivehi; the same answer again records
    // nothing, and says so — it said "recorded".
    $consent = ['consent_type' => 'marketing_messages', 'granted' => true];
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.consents.store', $student), $consent)
        ->assertSessionHas('success', $dv['flash_consent_recorded']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('people.students.consents.store', $student), $consent)
        ->assertSessionHas('success', $dv['flash_consent_unchanged']);
    expect(Consent::query()->where('person_id', $student->id)->count())->toBe(1);

    // A required custom field left empty is refused, by its Dhivehi label.
    $field = makeCustomFieldDefinition(['required' => true]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('people.students.custom-fields.update', $student), ['values' => [$field->id => '']])
        ->assertSessionHasErrors(['field_'.$field->id => __('people.error_field_required', ['field' => 'ލޭގެ ގްރޫޕް'], 'dv')]);
});
