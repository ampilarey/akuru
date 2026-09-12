<?php

use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §28.5 "Self-Learning Offering Version Mode":
 *
 *   > Default for self-learning offerings: Always latest published
 *   >
 *   > Scheduled offerings such as face-to-face, live online, blended, and
 *   > hybrid should default to **pinned** mode when the offering opens.
 *
 * `SaveCourseOfferingAction` defaulted **every** offering to `latest`,
 * whatever its delivery mode. So a face-to-face or live-online cohort had the
 * course content change underneath it mid-term unless an admin remembered to
 * pin — the opposite of what §28.5 asks, and the failure is silent: the
 * offering looks correctly configured.
 *
 * Self-learning is the only mode §28.5 puts on `latest`, and the reason is
 * structural: it is the only one without a cohort moving through the material
 * together.
 */
uses(RefreshDatabase::class);

function offeringCourseId(): int
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Pin default '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);

    return (int) $course->id;
}

function saveOffering(string $mode, ?string $pinMode = null)
{
    $data = [
        'course_id' => offeringCourseId(),
        'title' => ucfirst($mode).' offering '.uniqueFixtureSuffix(),
        'delivery_mode' => $mode,
    ];
    if ($pinMode !== null) {
        $data['pin_mode'] = $pinMode;
    }

    return app(SaveCourseOfferingAction::class)->execute($data);
}

it('leaves a self-learning offering on latest, as §28.5 specifies', function () {
    expect(saveOffering('self_learning')->pin_mode)->toBe('latest');
});

it('defaults a face-to-face offering to pinned', function () {
    // Previously `latest`: a classroom cohort's material could change
    // mid-term because a different course author published an edit.
    expect(saveOffering('face_to_face')->pin_mode)->toBe('pinned');
});

it('defaults live online, blended and hybrid to pinned too', function () {
    expect(saveOffering('live_online')->pin_mode)->toBe('pinned')
        ->and(saveOffering('blended')->pin_mode)->toBe('pinned')
        ->and(saveOffering('hybrid')->pin_mode)->toBe('pinned');
});

it('lets an explicit choice override the default in both directions', function () {
    // §28.5 sets a default, not a rule. An admin who wants a face-to-face
    // offering tracking the latest content may say so.
    expect(saveOffering('face_to_face', 'latest')->pin_mode)->toBe('latest')
        ->and(saveOffering('self_learning', 'pinned')->pin_mode)->toBe('pinned');
});

it('falls back to the mode default when the value is not a real pin mode', function () {
    // Garbage used to become `latest` for everything; now it becomes whatever
    // the delivery mode should have defaulted to.
    expect(saveOffering('face_to_face', 'whenever')->pin_mode)->toBe('pinned')
        ->and(saveOffering('self_learning', 'whenever')->pin_mode)->toBe('latest');
});

it('keeps the pin mode an update does not mention', function () {
    $offering = saveOffering('face_to_face');

    $updated = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $offering->course_id,
        'title' => 'Renamed offering',
        'delivery_mode' => 'face_to_face',
        'pin_mode' => 'pinned',
    ], $offering);

    expect($updated->pin_mode)->toBe('pinned');
});
