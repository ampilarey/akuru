<?php

use App\Domains\Courses\Actions\NormalizeTextAnswerAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\ValidateNormalizationSettingsAction;
use App\Domains\Courses\Enums\ActivitySubmissionKind;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §51 is largely built, and the audit says so before it says anything
 * else: letters and harakas are admin-managed through real CRUD routes
 * (§51.23 #3), the AI side has `AiPrediction`, `TrainingSample`,
 * `AiModelVersion` and `ActivateAiModelVersionAction` (#9, #12, #13), and the
 * pronunciation feature flag is threaded through so the platform runs with AI
 * off (#14).
 *
 * Two of §51.6's "Writing" requirements did not hold.
 *
 * **§51.8 lists "Remove tatweel ـ" among the normalization options and nothing
 * implemented it** — not `NormalizeTextAnswerAction`, not the settings
 * validator, not `SaveActivityAction`'s list. Tatweel (U+0640, the kashida) is
 * a decorative letter-stretcher with no phonetic value that arrives without
 * being typed: Arabic keyboards produce it and it survives a copy-paste out of
 * justified text. A student answering مـحـمـد was marked wrong against محمد
 * for characters that mean nothing.
 *
 * **§51.6 lists "Handwriting canvas" beside "Handwriting image upload"**, and
 * §51.23 #8 requires "Handwriting/canvas submissions are saved for teacher
 * review". The upload half has worked since §36 gave teacher-marked activities
 * a `file` kind that accepts images. The canvas did not exist — and it is the
 * half that matters for a child practising letterforms on a tablet, who has no
 * image to upload because the writing has not happened anywhere else yet.
 *
 * **Recorded, not built (rule 1 and §51.22).** §51.23 #11 "Admin can retrain
 * in batches" has no implementation anywhere. That is Module B, which §51.22
 * places after the general system is stable and CLAUDE.md rule 8 gates by
 * phase — so it is noted rather than started.
 */
uses(RefreshDatabase::class);

it('removes tatweel, which §51.8 names and nothing implemented', function () {
    $normalize = app(NormalizeTextAnswerAction::class);

    // محمد written with kashida between the letters — what an Arabic keyboard
    // and justified text actually produce.
    $withTatweel = 'مـحـمـد';
    $plain = 'محمد';

    expect($normalize->execute($withTatweel, ['strip_tatweel' => true]))->toBe($plain);
    // Off by default: §18 and §51.8 both say Arabic normalization is not global.
    expect($normalize->execute($withTatweel, []))->toBe($withTatweel);
});

it('keeps harakas when tatweel is removed', function () {
    // A haraka can sit on a tatweel. Stripping the stretcher must leave the
    // mark on the letter it belongs to, or a haraka question loses the very
    // thing it is checking.
    $normalize = app(NormalizeTextAnswerAction::class);

    $text = 'بِسْـمِ';
    $result = $normalize->execute($text, ['strip_tatweel' => true]);

    expect($result)->not->toContain("\u{0640}");
    // Kasra survives.
    expect($result)->toContain("\u{0650}");
});

it('accepts the setting through the validator, which derives its allowlist', function () {
    $clean = app(ValidateNormalizationSettingsAction::class)->execute(['strip_tatweel' => true]);

    expect($clean)->toBe(['strip_tatweel' => true]);
    expect(NormalizeTextAnswerAction::flags())->toContain('strip_tatweel');
});

it('carries every normalization flag through the activity settings', function () {
    // `SaveActivityAction` hardcodes its own list rather than deriving it from
    // `NormalizeTextAnswerAction::flags()`, so a flag added there is silently
    // dropped here — an author ticks a box and the normalizer never sees it.
    // That is exactly how `strip_tatweel` would have gone missing a second
    // time.
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Normalize '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    $settings = [];
    foreach (NormalizeTextAnswerAction::flags() as $flag) {
        $settings[$flag] = true;
    }

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Type it',
        'pattern' => 'text_input',
        'activity_type' => 'typing',
        'data' => ['prompt' => 'Type', 'acceptable' => ['محمد']],
        'settings' => ['normalize' => $settings],
    ]);

    $missing = array_values(array_diff(
        NormalizeTextAnswerAction::flags(),
        array_keys($activity->settings['normalize'] ?? []),
    ));

    expect($missing)->toBeEmpty(
        'SaveActivityAction drops these normalization flags, so an author can tick '
        .'them and the normalizer will never see them: '.implode(', ', $missing)
    );
});

it('adds §51.6 handwriting canvas as a submission kind', function () {
    expect(ActivitySubmissionKind::tryFrom('canvas'))->toBe(ActivitySubmissionKind::Canvas);
    expect(ActivitySubmissionKind::Canvas->isCanvas())->toBeTrue();
    expect(ActivitySubmissionKind::Canvas->acceptsUploads())->toBeTrue();

    // A canvas exports an image and nothing else — narrow on purpose, the same
    // way `audio` is.
    expect(ActivitySubmissionKind::Canvas->allowedMimes())->toContain('image/png');
    expect(ActivitySubmissionKind::Canvas->allowedMimes())->not->toContain('application/pdf');

    // The other kinds are not canvases, so the player cannot confuse them.
    expect(ActivitySubmissionKind::File->isCanvas())->toBeFalse();
    expect(ActivitySubmissionKind::Audio->isCanvas())->toBeFalse();
});

it('tells the player to draw rather than offer a file picker', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Handwriting '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write the letter',
        'pattern' => 'teacher_marked',
        'activity_type' => 'handwriting',
        'data' => ['prompt' => 'Write ba', 'submission_kind' => 'canvas'],
    ]);

    expect($activity->data['submission_kind'])->toBe('canvas');

    $definition = app(\App\Domains\Courses\Actions\ResolveActivityDefinitionAction::class)->execute($activity->id);
    expect($definition['submission']['is_canvas'])->toBeTrue();
    expect($definition['submission']['accepts_text'])->toBeFalse();
    expect($definition['submission']['label'])->toBe('Handwriting');
});

it('posts the drawing through the one upload path §36 already built', function () {
    // §51.6: Arabic skills "must be implemented using the general platform
    // activity system. Do not create a separate Arabic exercise engine." The
    // canvas exports a PNG and travels the same attempt-media route as every
    // other attachment — one endpoint, one allowlist, one server-owned list.
    $source = (string) file_get_contents(base_path('resources/js/Components/HandwritingCanvas.jsx'));

    expect($source)->toContain('toBlob');
    expect($source)->toContain('image/png');
    // The component takes a callback and does not know the URL: the page hands
    // it the same `upload` used by the file input.
    expect($source)->not->toContain('/learn/activities/');

    $page = (string) file_get_contents(base_path('resources/js/Pages/Courses/Learn/Activity.jsx'));
    expect($page)->toContain('HandwritingCanvas');
    expect($page)->toContain('is_canvas');
});
