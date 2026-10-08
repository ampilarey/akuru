<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

/**
 * Laravel's own validation messages in Dhivehi and Arabic (BACKLOG C19,
 * slice CT6b-1, STATUS §5oz).
 *
 * The framework ships its messages in English alone, and `resources/lang` had
 * no `validation.php`: a form refused on a Dhivehi or Arabic page said why in
 * English, under labels in Thaana or Arabic. The two files carry every key of
 * the framework's English file. A Laravel upgrade that adds a rule adds a key
 * there, and the first test names it.
 */
function laravelValidationSentences(?string $locale = null): array
{
    $file = $locale === null
        ? require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php')
        : require resource_path("lang/{$locale}/validation.php");

    // `custom`, `attributes` and `values` are the app's own, not sentences.
    return Arr::dot(Arr::except($file, ['custom', 'attributes', 'values']));
}

/** @return list<string> the `:placeholders` a sentence fills in, sorted */
function sentencePlaceholders(string $sentence): array
{
    preg_match_all('/:[a-z_]+/', $sentence, $found);
    $names = array_values(array_unique($found[0]));
    sort($names);

    return $names;
}

function refusedSessionOffering(): CourseOffering
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Refusal Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);

    return app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Evening class',
        'delivery_mode' => 'live_online',
        'status' => 'open',
        'created_by' => $admin->id,
    ]);
}

dataset('page languages', [
    'Dhivehi' => ['dv', '/\p{Thaana}/u'],
    'Arabic' => ['ar', '/\p{Arabic}/u'],
]);

it('says every one of Laravel’s messages, with the same placeholders', function (string $locale, string $script) {
    $english = laravelValidationSentences();
    $translated = laravelValidationSentences($locale);

    expect(array_keys(array_diff_key($english, $translated)))->toBe([], "Laravel messages missing from lang/{$locale}/validation.php")
        ->and(array_keys(array_diff_key($translated, $english)))->toBe([], "lang/{$locale}/validation.php has keys Laravel does not");

    foreach ($english as $key => $sentence) {
        expect(sentencePlaceholders($translated[$key]))->toBe(sentencePlaceholders($sentence), "{$locale}.{$key} fills in other placeholders")
            ->and($translated[$key])->toMatch($script, "{$locale}.{$key} is not in the page's script");
    }
})->with('page languages');

it('names the same fields in both languages, each in its own script', function () {
    $dhivehi = require resource_path('lang/dv/validation.php');
    $arabic = require resource_path('lang/ar/validation.php');

    expect(array_keys($dhivehi['attributes']))->toEqualCanonicalizing(array_keys($arabic['attributes']))
        ->and(array_keys(Arr::dot($dhivehi['values'])))->toEqualCanonicalizing(array_keys(Arr::dot($arabic['values'])))
        // A named "today" in a sentence about an unnamed field is still half English.
        ->and(array_diff(array_keys($dhivehi['values']), array_keys($dhivehi['attributes'])))->toBe([]);

    foreach ($dhivehi['attributes'] + Arr::dot($dhivehi['values']) as $field => $name) {
        expect($name)->toMatch('/\p{Thaana}/u', "dv {$field}");
    }
    foreach ($arabic['attributes'] + Arr::dot($arabic['values']) as $field => $name) {
        expect($name)->toMatch('/\p{Arabic}/u', "ar {$field}");
    }
});

it('refuses a session from a Dhivehi page in Dhivehi, naming the fields as the form does', function () {
    $offering = refusedSessionOffering();

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url("/dv/catalog/offerings/{$offering->id}/sessions"))
        ->post("/catalog/offerings/{$offering->id}/sessions", [
            'title' => '',
            'session_type' => 'live_online',
            'starts_at' => '2026-10-10 09:00',
            'ends_at' => '2026-10-10 08:00',
        ])
        ->assertSessionHasErrors([
            'title' => 'ނަން ބޭނުންވޭ.',
            // `after:starts_at` names the other field too, in Dhivehi.
            'ends_at' => 'ނިމޭ ވަގުތު އަކީ ފަށާ ވަގުތު ގެ ފަހުގެ ތާރީޚަކަށް ވާންޖެހޭ.',
        ]);
});

it('refuses a term from an Arabic page in Arabic', function () {
    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url('/ar/catalog/glossary'))
        ->post('/catalog/glossary', ['term' => ''])
        ->assertSessionHasErrors(['term' => 'حقل المصطلح مطلوب.']);
});

it('leaves an English page in Laravel’s English', function () {
    $offering = refusedSessionOffering();

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url("/en/catalog/offerings/{$offering->id}/sessions"))
        ->post("/catalog/offerings/{$offering->id}/sessions", ['title' => '', 'session_type' => 'live_online', 'starts_at' => '2026-10-10 09:00'])
        ->assertSessionHasErrors(['title' => 'The title field is required.']);
});

it('says the "today" a birth date is compared with in the page’s language', function () {
    // The registration forms check `dob` against `before:today`; without
    // `values` the sentence would carry the English word.
    app()->setLocale('dv');
    expect(validator(['dob' => '2999-01-01'], ['dob' => 'date|before:today'])->errors()->first('dob'))
        ->toBe('އުފަން ތާރީޚު އަކީ މިއަދު ގެ ކުރީގެ ތާރީޚަކަށް ވާންޖެހޭ.');

    app()->setLocale('ar');
    expect(validator(['dob' => '2999-01-01'], ['dob' => 'date|before:today'])->errors()->first('dob'))
        ->toBe('يجب أن يكون حقل تاريخ الميلاد تاريخًا قبل اليوم.');
});
