<?php

use App\Domains\Courses\Actions\NormalizeBlockTextSettingsAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §15.3 "Text Direction Rule":
 *
 *   > Text direction and content language are settings on every text-capable
 *   > block, not separate block types.
 *   >
 *   > Text-capable blocks must support settings such as:
 *   > - Content language
 *   > - Direction: LTR, RTL, or auto
 *   > - Text alignment using start/end
 *   > - Optional font preference
 *   >
 *   > Arabic and Dhivehi/Thaana support must be handled through block
 *   > settings, localization, fonts, and logical CSS, not by creating a
 *   > separate block type.
 *
 * The structural half was already right, and stays cleared: there is no
 * `RtlText` case, and the player reads `settings.direction` instead of
 * branching per type. **Four of the five settings did not exist.** A lesson
 * could not say an Arabic passage was Arabic, could not right-align a Thaana
 * note, and could not ask for a Thaana face — the three things §15.3 exists to
 * make possible without a second block type.
 *
 * The quieter defect underneath: `CourseOutlineController` assigned
 * `'settings' => ['direction' => ...]`, a **whole new array**, on every save.
 * Any other setting a block carried was destroyed the next time anyone touched
 * it, so adding four settings on top would have shipped four fields that
 * silently reset.
 */
uses(RefreshDatabase::class);

/**
 * Settings sorted by key, because the key *order* of a JSON column is not
 * ours to assert.
 *
 * MariaDB stores JSON as text and preserves insertion order. **MySQL 8 stores
 * a native JSON type and normalizes object keys — sorted by key length, then
 * lexicographically.** So the same four settings come back as
 * `direction, align, language, font` locally and
 * `font, align, language, direction` on CI, and `toBe()` on an associative
 * array is order-sensitive.
 *
 * This test asserted the exact array and passed locally against MariaDB, then
 * failed on CI against MySQL 8 with every value correct and only the order
 * different. Sorting makes the assertion about what it is actually claiming.
 */
function settingsByKey(?array $settings): array
{
    $settings ??= [];
    ksort($settings);

    return $settings;
}

function textSettingsLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Text settings '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Unit',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson',
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'module', 'lesson');
}

it('has no separate RTL block type, as §15.3 requires', function () {
    $values = array_map(fn (ContentBlockType $t): string => $t->value, ContentBlockType::cases());

    expect($values)->not->toContain('rtl_text')
        ->and($values)->not->toContain('rtl');
});

it('carries every block type §15.1 and §15.2 name', function () {
    $values = array_map(fn (ContentBlockType $t): string => $t->value, ContentBlockType::cases());

    foreach ([
        // §15.1 Phase 1A
        'text', 'rich_text', 'image', 'audio', 'video', 'pdf', 'instruction',
        // §15.2 Phase 1B
        'glossary', 'term', 'dialogue', 'flashcard', 'download', 'quiz_embed', 'assignment_embed',
    ] as $type) {
        expect($values)->toContain($type);
    }
});

it('stores all four §15.3 settings from the block form', function () {
    // The gap. Only `direction` was validated, and `validate()` returns only
    // what it validates, so the other three were dropped however they arrived.
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = textSettingsLesson();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks", [
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'body' => 'وَالسَّلَامُ',
            'direction' => 'rtl',
            'align' => 'end',
            'language' => 'ar',
            'font' => 'arabic',
        ])
        ->assertRedirect();

    expect(settingsByKey(ContentBlock::query()->latest('id')->first()->settings))
        ->toBe(['align' => 'end', 'direction' => 'rtl', 'font' => 'arabic', 'language' => 'ar']);
});

it('refuses physical alignment, because §15.3 says start/end', function () {
    // `left`/`right` are silently wrong the moment the same block is read in
    // the other direction, which is the whole point of the section.
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = textSettingsLesson();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks", [
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'body' => 'Body',
            'align' => 'left',
        ])
        ->assertSessionHasErrors('align');
});

it('merges settings instead of replacing them', function () {
    // The quiet defect. A whole new array was assigned on every save, so any
    // setting not in that array was destroyed the next time anyone saved.
    $normalize = app(NormalizeBlockTextSettingsAction::class);

    $merged = $normalize->execute(
        ['direction' => 'rtl', 'font' => 'thaana'],
        ['align' => 'end'],
    );

    expect($merged)->toBe(['direction' => 'rtl', 'font' => 'thaana', 'align' => 'end']);
});

it('clears a setting when it is explicitly emptied', function () {
    $normalize = app(NormalizeBlockTextSettingsAction::class);

    expect($normalize->execute(['font' => 'thaana'], ['font' => '']))->toBe([]);
});

it('drops a value no stylesheet understands rather than storing it', function () {
    // A block whose alignment is a word nothing recognises would render
    // unaligned with nothing on screen to say why.
    $normalize = app(NormalizeBlockTextSettingsAction::class);

    expect($normalize->execute([], ['align' => 'justify-ish', 'direction' => 'sideways']))->toBe([]);
});

it('falls back to logical defaults when a block says nothing', function () {
    $resolved = app(NormalizeBlockTextSettingsAction::class)->resolved(null);

    expect($resolved)->toBe([
        'direction' => 'auto',
        'align' => 'start',
        'language' => 'auto',
        'font' => 'default',
    ]);
});

it('applies the settings to media blocks too, which carry titles and captions', function () {
    // A Thaana caption under an image needs the same direction and face a
    // Thaana paragraph does. §15.3's rule is about text, wherever it appears.
    $applies = app(NormalizeBlockTextSettingsAction::class);

    expect($applies->appliesTo(ContentBlockType::Image))->toBeTrue()
        ->and($applies->appliesTo(ContentBlockType::Text))->toBeTrue()
        ->and($applies->appliesTo(ContentBlockType::QuizEmbed))->toBeFalse();
});

it('carries the settings into the published revision snapshot', function () {
    // §28.1: what the snapshot omits, a published revision cannot honour. A
    // revision that lost its direction would render an Arabic passage
    // left-to-right for every enrolled student.
    ['admin' => $admin, 'lesson' => $lesson] = textSettingsLesson();
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'نص'],
        'settings' => ['direction' => 'rtl', 'align' => 'end', 'language' => 'ar', 'font' => 'arabic'],
        'created_by' => $admin->id,
    ]);

    $revision = app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    expect(settingsByKey($revision->snapshot_json['blocks'][0]['settings']))
        ->toBe(['align' => 'end', 'direction' => 'rtl', 'font' => 'arabic', 'language' => 'ar']);
});
