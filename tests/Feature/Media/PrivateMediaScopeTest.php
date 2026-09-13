<?php

use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StoreMediaContentBlockAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * `GET /learn/media/{media}` serves a private file, and `ServeCatalogMediaAction`
 * decides who may have it. For a student that decision is a careful allow-list:
 * the file has to be referenced by a preview lesson, by one of their own attempt
 * snapshots, or by a lesson on a course they are enrolled in. That half is well
 * built and this file does not argue with it.
 *
 * The staff half is one line:
 *
 *     $allowed = ($user->can('courses.manage')) || $this->studentMayView(...);
 *
 * and `ReadPrivateMediaAction` behind it is `MediaFile::find($id)` with no
 * scope at all. So the permission is read as "may manage courses" and spends as
 * **"may read every private file in the application"** — every domain, by id,
 * with no relationship to a course required.
 *
 * What is actually stored privately, from the twelve callers of
 * `StorePrivateMediaAction`: children's Qur'an recitation recordings, children's
 * pronunciation attempts, students' uploaded activity submissions, students'
 * work photographs, lost-property photographs, class materials, and the Library's
 * paid PDF originals — the last being the one thing LIBRARY_PLAN §36 says must
 * never be exposed, and the reason the protected reader exists at all.
 *
 * `courses.manage` is held by super_admin, admin, headmaster, supervisor **and
 * course_creator**. §8 deliberately withholds `courses.publish` from a course
 * creator, so the intent that role should be limited is on the record; handing
 * it every child's recording in the school is not that.
 */
it('does not let a course permission fetch another domain\'s private file', function () {
    // A file with no relationship to any course: this stands in for a Qur'an
    // recitation, a pronunciation attempt, or a Library PDF, all of which reach
    // storage through exactly this action.
    $stored = app(StorePrivateMediaAction::class)->execute(
        UploadedFile::fake()->create('recitation.mp3', 16, 'audio/mpeg'),
        null,
        ['audio/mpeg'],
    );

    $courseCreator = User::factory()->create();
    $courseCreator->givePermissionTo('courses.manage');

    $this->withoutLocalizationMiddleware()
        ->actingAs($courseCreator)
        ->get(route('learn.media.show', ['media' => $stored['id']]))
        ->assertForbidden();
});

it('still serves a file the staff member has a course reason to see', function () {
    // The check must narrow, not break: this same endpoint is how a catalog
    // manager views the media on a lesson they are building.
    Storage::fake('local');
    Queue::fake();

    $manager = actingPeopleAdmin(['courses.manage']);

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Media scope '.uniqid(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $manager->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $manager->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Sounds', 'created_by' => $manager->id,
    ]);

    $block = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'image',
        'file' => UploadedFile::fake()->image('chart.png', 12, 12),
        'created_by' => $manager->id,
    ]);

    $mediaId = (int) ($block->data['media_id'] ?? 0);
    expect($mediaId)->toBeGreaterThan(0);

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->get(route('learn.media.show', ['media' => $mediaId]))
        ->assertOk();
});

it('refuses a signed-in account with no course permission at all', function () {
    $stored = app(StorePrivateMediaAction::class)->execute(
        UploadedFile::fake()->create('recitation.mp3', 16, 'audio/mpeg'),
        null,
        ['audio/mpeg'],
    );

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('learn.media.show', ['media' => $stored['id']]))
        ->assertForbidden();
});
