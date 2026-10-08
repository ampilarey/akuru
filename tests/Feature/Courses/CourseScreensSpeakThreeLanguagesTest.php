<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Enums\CourseReviewDecision;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\LessonStatus;
use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The course-building screens in Dhivehi and Arabic (slices CT1–CT3, STATUS
 * §5ok on). The owner, 2026-10-08: "Leave everything from my side. Continue
 * from ur side" — and the first thing on this side was that the screens
 * teachers build courses on were English only.
 *
 * Every string on a translated screen is `t.key || 'English'`. This reads the
 * screen's source and holds three things: the key is in the `teach` book in
 * all three languages, the book's English is exactly the screen's fallback (so
 * an English page reads the same either way), and no bare English is left in
 * a text node, a placeholder or a label.
 */
uses(RefreshDatabase::class);

/** The screens translated so far; each slice adds its own. */
function translatedCourseScreens(): array
{
    return [
        'Courses/Catalog/Index',
        'Courses/Catalog/Outline',
        'Courses/Catalog/Rubrics',
    ];
}

/** The same in every language, on purpose. */
function sameInEveryLanguage(): array
{
    return ['block_pdf'];
}

function teachBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/teach.php");
}

/**
 * Fields a screen reader has no name for: no aria-label, no id for a label to
 * point at, and not inside a label.
 */
function unnamedFields(string $source): array
{
    preg_match_all('/<(select|input|textarea)\b(.*?)(\/>|<option|\.map\(|<\/select>)/s', $source, $fields, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $unnamed = [];
    foreach ($fields as $field) {
        [$attributes, $at] = $field[2];
        $before = substr($source, max(0, $at - 300), min(300, $at));
        $inLabel = strrpos($before, '<label') !== false && strrpos($before, '<label') > (int) strrpos($before, '</label>');
        if (! str_contains($attributes, 'aria-label=') && ! preg_match('/\sid=/', $attributes) && ! $inLabel && ! str_contains($attributes, 'type="hidden"')) {
            $unnamed[] = substr(preg_replace('/\s+/', ' ', trim($attributes)), 0, 80);
        }
    }

    return $unnamed;
}

it('keys every string on the translated course screens in three languages', function () {
    [$en, $dv, $ar] = [teachBook('en'), teachBook('dv'), teachBook('ar')];

    foreach (translatedCourseScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: teach.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: teach.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: teach.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: teach.{$key} says something else in English than the screen");
            if (! in_array($key, sameInEveryLanguage(), true)) {
                expect($dv[$key])->not->toBe($en[$key], "teach.{$key} is English in Dhivehi")
                    ->and($ar[$key])->not->toBe($en[$key], "teach.{$key} is English in Arabic");
            }
        }

        // No bare English: a text node (a word or an abbreviation like LTR),
        // a placeholder or a label written out.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every status, decision, unlock rule and block type the server sends', function () {
    $book = fn (string $locale) => teachBook($locale);
    $needed = [
        ...array_map(fn ($case) => 'decision_'.$case->value, CourseReviewDecision::cases()),
        ...array_map(fn ($case) => 'unlock_'.$case->value, UnlockMode::cases()),
        ...array_map(fn ($case) => 'workflow_'.$case->value, CourseWorkflowStatus::cases()),
        ...array_map(fn ($case) => 'status_'.$case->value, [...LessonStatus::cases(), ...ModuleStatus::cases()]),
        ...array_map(fn ($case) => 'block_'.$case->value, ContentBlockType::cases()),
    ];

    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach ($needed as $key) {
            expect(array_key_exists($key, $book($locale)))->toBeTrue("teach.{$key} is missing in {$locale}");
        }
    }
});

it('serves the catalog and the outline in Dhivehi, and says what was saved in the language of the page', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Fiqh one',
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Index')
            ->where('t.catalog_title', trans('teach.catalog_title', [], 'dv'))
            ->where('t.workflow_draft', 'ޑްރާފްޓް'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.outline', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Outline')
            ->where('t.outline_save_module', trans('teach.outline_save_module', [], 'dv')));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.modules.store', $course->id), ['title' => 'Purity'])
        ->assertSessionHas('success', trans('teach.flash_module_saved', [], 'dv'));
    expect(CourseModule::query()->where('course_id', $course->id)->value('title'))->toBe('Purity');

    app()->setLocale('en');
    expect(__('teach.flash_module_saved'))->toBe('Module saved.');
});

it('names the seeded subjects in Dhivehi and Arabic for the catalog, and keeps a name somebody typed', function () {
    $subjects = CourseSubject::query()->get(['slug', 'name_en', 'name_dv', 'name_ar']);
    expect($subjects)->toHaveCount(17);
    foreach ($subjects as $subject) {
        expect($subject->name_dv)->not->toBeEmpty("{$subject->slug} has no Dhivehi name")
            ->and($subject->name_ar)->not->toBeEmpty("{$subject->slug} has no Arabic name")
            ->and($subject->name_dv)->not->toBe($subject->name_en);
    }

    $this->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin(['courses.manage']))
        ->get(route('catalog.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('subjects', fn ($rows) => collect($rows)->firstWhere('slug', 'fiqh')['name_ar'] === 'الفقه'));

    CourseSubject::query()->where('slug', 'hadith')->update(['name_dv' => 'ޙަދީޘް ދިރާސާ', 'name_ar' => null]);
    (require database_path('migrations/2026_10_08_000001_course_subject_names_in_dhivehi_and_arabic.php'))->up();
    expect(CourseSubject::query()->where('slug', 'hadith')->first())
        ->name_dv->toBe('ޙަދީޘް ދިރާސާ')
        ->name_ar->toBe('الحديث');
});
