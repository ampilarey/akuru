<?php

use App\Domains\Forms\Models\Form;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/**
 * The office's sign-up forms and the school's events in Dhivehi and Arabic
 * (BACKLOG C21, slice SE1, STATUS §5qr).
 *
 * Four office screens read no phrase book: the sign-up forms and a form's
 * results, the events and an event. Every word on them was English; a
 * question's type, a form's state, an event's type, state and registration
 * and a registration's state were printed as codes; an event read by its
 * English title alone; the server's saved messages and refusals were
 * English, and the names Laravel's refusals gave a form's questions too (a
 * blank question read *question ބޭނުންވޭ*). A refused Close, Confirm or
 * second round was said nowhere, nor most of the event form's refusals; the
 * events list could not be read for a year or a state, though the server
 * took both; and a second event with an English title an earlier one had was
 * refused, keyed to an address the form has no box for — so it was said
 * nowhere either. The family's side of a form refused a child that was not
 * theirs in English.
 */
uses(RefreshDatabase::class);

/** The four screens; every phrase on them is `t.key || 'English'`, from the `academics` book. */
function formsEventsScreens(): array
{
    return ['Forms/Index', 'Forms/Results', 'Website/Events/Index', 'Website/Events/Show'];
}

/** Where the server writes what those screens say. */
function formsEventsServerFiles(): array
{
    return [
        'app/Domains/Forms/Http/Controllers/FormAdminController.php',
        'app/Domains/Forms/Actions/SaveFormAction.php',
        'app/Domains/Forms/Actions/ResolveResponseStudentAction.php',
        'app/Domains/Website/Http/Controllers/EventAdminController.php',
        'app/Domains/Website/Actions/SaveEventAction.php',
    ];
}

function academicsBookForSe1(string $locale): array
{
    return require base_path("resources/lang/{$locale}/academics.php");
}

/** The office: the events live under `/academics`, which the office's roles open. */
function actingFormsAndEventsOffice(): User
{
    $user = User::factory()->create();
    foreach (['forms.manage', 'events.manage'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
    $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

    return $user;
}

function makeEventForSe1(array $overrides = []): Event
{
    return app(SaveEventAction::class)->execute(array_merge([
        'title' => 'Sports Day',
        'title_dv' => 'ކުޅިވަރު ދުވަސް',
        'location' => 'Field',
        'start_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'type' => 'competition',
        'status' => 'published',
        'registration_type' => 'required',
        'max_attendees' => 1,
        'min_attendees' => 2,
        'waitlist_enabled' => true,
        'requires_parent_confirmation' => true,
        'is_elective' => true,
        'is_public' => false,
    ], $overrides));
}

it('keys every string on the forms and events screens in three languages', function () {
    [$en, $dv, $ar] = [academicsBookForSe1('en'), academicsBookForSe1('dv'), academicsBookForSe1('ar')];

    foreach (formsEventsScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: academics.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: academics.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: academics.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: academics.{$key} says something else in English than the screen");
            if (trim($en[$key], ' ,') !== '') {
                expect($dv[$key])->not->toBe($en[$key], "academics.{$key} is English in Dhivehi")
                    ->and($ar[$key])->not->toBe($en[$key], "academics.{$key} is English in Arabic");
            }
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }
});

it('names every field the forms and events screens post, so a refusal by Laravel’s own rules reads whole in Dhivehi and Arabic', function () {
    $dhivehi = (require resource_path('lang/dv/validation.php'))['attributes'];
    $arabic = (require resource_path('lang/ar/validation.php'))['attributes'];

    foreach (array_filter(formsEventsServerFiles(), fn (string $file) => str_contains($file, '/Controllers/')) as $file) {
        $source = file_get_contents(base_path($file));
        preg_match_all("/'([a-z_]+(?:\\.\\*(?:\\.[a-z_]+)?)?)' => \\[(?=[^\\]]*'(?:required|nullable|sometimes|integer|string|array|boolean|date|numeric)')/", $source, $found);
        expect($found[1])->not->toBeEmpty("{$file} validates nothing the test can read");

        foreach (array_unique($found[1]) as $field) {
            // A question's fields are named by the controller itself, from the book.
            $namedHere = preg_match('/\''.preg_quote($field, '/').'\' => __\(\'academics\.attr_/', $source) === 1;
            expect($namedHere || array_key_exists($field, $dhivehi))->toBeTrue("{$field} has no Dhivehi name")
                ->and($namedHere || array_key_exists($field, $arabic))->toBeTrue("{$field} has no Arabic name");
        }
    }
});

it('names every code the forms and events screens show, in all three languages', function () {
    $codes = [
        ...array_map(fn ($type) => 'form_type_'.$type, ['text', 'textarea', 'select', 'multi_select', 'yes_no', 'date']),
        ...array_map(fn ($type) => 'event_type_'.$type, ['conference', 'workshop', 'seminar', 'competition', 'celebration', 'meeting', 'other']),
        ...array_map(fn ($status) => 'event_status_'.$status, ['draft', 'published', 'cancelled', 'completed']),
        ...array_map(fn ($type) => 'event_registration_'.$type, ['none', 'required', 'optional']),
        ...array_map(fn ($status) => 'registration_status_'.$status, ['confirmed', 'pending', 'pending_parent', 'waitlisted', 'cancelled', 'attended', 'no_show']),
    ];
    // The codes the server offers are the codes the book names.
    expect(array_map(fn ($case) => $case->value, \App\Domains\Forms\Enums\FormFieldType::cases()))
        ->toEqualCanonicalizing(['text', 'textarea', 'select', 'multi_select', 'yes_no', 'date']);

    foreach ($codes as $key) {
        expect(trans("academics.{$key}", [], 'en'))->not->toBe("academics.{$key}", "academics.{$key} has no English")
            ->and(trans("academics.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "academics.{$key} in Dhivehi")
            ->and(trans("academics.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "academics.{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the forms and events screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (formsEventsServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(formsEventsServerFiles());
    expect($keys)->toContain('academics.flash_form_saved', 'academics.error_form_options', 'academics.attr_question',
        'academics.flash_event_second_round', 'academics.error_event_slug', 'portal.error_form_not_your_child');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('serves the forms screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $office = actingFormsAndEventsOffice();
    $dv = academicsBookForSe1('dv');
    app()->setLocale('dv');

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('forms.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Forms/Index')->where('t.forms_title', $dv['forms_title']));

    // A blank question is refused by its Dhivehi name — it read *question ބޭނުންވޭ*.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('forms.store'), ['title' => 'Trip', 'fields' => [['label' => '', 'type' => 'text']], 'is_published' => true])
        ->assertSessionHasErrors('fields.0.label');
    expect(session('errors')->first('fields.0.label'))->toContain($dv['attr_question'])->not->toMatch('/[A-Za-z]{3,}/');

    // A choice with one option is refused in Dhivehi, by the question's own words.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('forms.store'), ['title' => 'Trip', 'fields' => [['label' => 'Colour', 'type' => 'select', 'options' => ['Red']]], 'is_published' => true])
        ->assertSessionHasErrors(['fields.0.options' => __('academics.error_form_options', ['label' => 'Colour'], 'dv')]);

    // An anonymous form with a fee is refused in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('forms.store'), ['title' => 'Survey', 'fields' => [['label' => 'How was it?', 'type' => 'text']], 'is_anonymous' => true, 'fee_amount' => 10])
        ->assertSessionHasErrors(['fee_amount' => $dv['error_form_anonymous_fee']]);

    // A form is saved, said in Dhivehi; its results read in Dhivehi; closed, said.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('forms.store'), ['title' => 'Trip', 'fields' => [['label' => 'Coming?', 'type' => 'yes_no', 'required' => true]], 'is_published' => true])
        ->assertSessionHas('success', $dv['flash_form_saved']);
    $form = Form::query()->where('title', 'Trip')->sole();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('forms.results', $form))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Forms/Results')->where('t.results_close_now', $dv['results_close_now']));
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('forms.update', $form), ['title' => 'Trip', 'fields' => $form->fields, 'is_published' => true, 'closes_at' => now()->toIso8601String()])
        ->assertSessionHas('success', $dv['flash_form_updated']);
    expect($form->fresh()->isOpen())->toBeFalse();
});

it('refuses a family a child that is not theirs in their language', function () {
    $mine = makeStudent(['first_name' => 'Mine']);
    $other = makeStudent(['first_name' => 'Other']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($mine, $guardian, 'father', true);
    $parent = User::query()->findOrFail($guardian->user_id);
    \Spatie\Permission\Models\Role::findOrCreate('parent', 'web');
    $parent->assignRole('parent');
    $form = Form::query()->create([
        'created_by' => $parent->id, 'title' => 'Trip', 'is_published' => true, 'target_audience' => ['parents'],
        'fields' => [['key' => 'f1', 'label' => 'Coming?', 'type' => 'yes_no', 'options' => [], 'required' => true]],
    ]);

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.forms.submit', $form->id), ['answers' => ['f1' => 'yes'], 'student_id' => $other->id])
        ->assertSessionHasErrors(['student_id' => __('portal.error_form_not_your_child', [], 'dv')]);
});

it('serves the events screens in Dhivehi, reads them for a year and a state, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $office = actingFormsAndEventsOffice();
    $dv = academicsBookForSe1('dv');
    app()->setLocale('dv');

    // An event with no location is refused by its Dhivehi name.
    $event = ['title' => 'Sports Day', 'title_dv' => 'ކުޅިވަރު ދުވަސް', 'location' => '', 'start_date' => now()->addWeek()->format('Y-m-d H:i:s'),
        'type' => 'competition', 'status' => 'published', 'registration_type' => 'required', 'academic_year_id' => $year->id];
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.events.store'), $event)
        ->assertSessionHasErrors('location');
    expect(session('errors')->first('location'))->toContain(__('validation.attributes.location', [], 'dv'));

    // Saved, said in Dhivehi; next year's with the same English title is
    // saved too — it was refused, keyed to an address the form has no box for.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.events.store'), [...$event, 'location' => 'Field'])
        ->assertSessionHas('success', $dv['flash_event_saved']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.events.store'), [...$event, 'location' => 'Field', 'status' => 'draft', 'start_date' => now()->addYear()->format('Y-m-d H:i:s')])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', $dv['flash_event_saved']);
    expect(Event::query()->where('title', 'Sports Day')->orderBy('id')->pluck('slug')->all())->toBe(['sports-day', 'sports-day-2']);

    // Saving the first again keeps its address.
    $first = Event::query()->where('slug', 'sports-day')->sole();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('academics.events.update', $first), [...$event, 'location' => 'Hall'])
        ->assertSessionHas('success', $dv['flash_event_updated']);
    expect($first->fresh()->slug)->toBe('sports-day');

    // The list reads for a state, and says which it was asked for.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('academics.events.index', ['status' => 'draft']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Events/Index')
            ->where('t.events_title', $dv['events_title'])
            ->where('filters.status', 'draft')
            ->has('events', 1)
            ->where('events.0.status', 'draft'));

    // A registration is saved, said in Dhivehi; a confirm of one not waiting
    // for a parent is refused in Dhivehi; the second round says what it did.
    $student = makeStudent(['first_name' => 'Aisha']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.events.register', $first), ['student_id' => $student->id])
        ->assertSessionHas('success', $dv['flash_event_registration_saved']);
    $registration = EventRegistration::query()->where('event_id', $first->id)->sole();
    $registration->update(['status' => 'confirmed']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.events.confirm', [$first, $registration->id]))
        ->assertSessionHasErrors(['status' => __('portal.error_event_not_awaiting', [], 'dv')]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.events.second-round', $first))
        ->assertSessionHas('success', __('academics.flash_event_second_round', ['count' => 0], 'dv'));

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('academics.events.show', $first))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Events/Show')->where('t.events_save', $dv['events_save'])->where('event.title_dv', 'ކުޅިވަރު ދުވަސް'));
});
