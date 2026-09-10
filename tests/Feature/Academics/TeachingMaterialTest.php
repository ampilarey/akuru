<?php

use App\Domains\Academics\Actions\AttachMaterialsToLessonAction;
use App\Domains\Academics\Actions\ListTeachingMaterialsAction;
use App\Domains\Academics\Actions\SaveTeachingMaterialAction;
use App\Domains\Academics\Models\TeachingMaterial;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E13a — a material a teacher can reuse.
 *
 * `lesson_logs.materials` is a free-text JSON array typed as a comma-separated
 * string, so "Textbook p.12, worksheet" is retyped every lesson and nothing is
 * searchable. These cover the library, its ownership rule, and the attachment.
 */
it('saves a material with normalised tags', function () {
    $author = User::factory()->create();

    $material = app(SaveTeachingMaterialAction::class)->execute([
        'title' => '  Alphabet worksheet  ',
        'body' => 'Print double sided.',
        // The form sends a string; search must never have to guess the shape.
        'tags' => 'Worksheet, printable ,worksheet,,',
    ], (int) $author->id);

    expect($material->title)->toBe('Alphabet worksheet')
        ->and($material->tags)->toBe(['worksheet', 'printable'])
        ->and((int) $material->created_by)->toBe((int) $author->id);
});

it('refuses a material with no title', function () {
    app(SaveTeachingMaterialAction::class)->execute(['title' => '   '], (int) User::factory()->create()->id);
})->throws(ValidationException::class);

it('lets only the author edit their own material', function () {
    $author = User::factory()->create();
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Mine'], (int) $author->id);

    // Shared visibility must not mean somebody else rewrites your wording
    // under your name.
    app(SaveTeachingMaterialAction::class)
        ->execute(['title' => 'Theirs'], (int) User::factory()->create()->id, $material);
})->throws(ValidationException::class);

it('finds materials by title, body, subject and tag', function () {
    $author = User::factory()->create();
    $subject = makeSubject();
    $save = app(SaveTeachingMaterialAction::class);

    $save->execute(['title' => 'Alphabet worksheet', 'subject_id' => $subject->id, 'tags' => 'printable'], (int) $author->id);
    $save->execute(['title' => 'Number chant', 'body' => 'Sing the alphabet after.'], (int) $author->id);
    $save->execute(['title' => 'Wall poster', 'tags' => 'display'], (int) User::factory()->create()->id);

    $list = app(ListTeachingMaterialsAction::class);

    // A library you can only scroll is the comma-separated string it replaces.
    expect($list->execute(['q' => 'alphabet'])->pluck('title')->all())
        ->toBe(['Alphabet worksheet', 'Number chant'])
        ->and($list->execute(['subject_id' => (int) $subject->id])->pluck('title')->all())
        ->toBe(['Alphabet worksheet'])
        ->and($list->execute(['tag' => 'display'])->pluck('title')->all())
        ->toBe(['Wall poster'])
        ->and($list->execute(['mine_for' => (int) $author->id])->pluck('title')->all())
        ->toBe(['Alphabet worksheet', 'Number chant'])
        ->and($list->execute()->first()['author'])->toBe($author->name);
});

it('offers general materials alongside the subject ones', function () {
    $author = User::factory()->create();
    $subject = makeSubject();
    $save = app(SaveTeachingMaterialAction::class);
    $save->execute(['title' => 'Arabic reader', 'subject_id' => $subject->id], (int) $author->id);
    $save->execute(['title' => 'Class rules handout'], (int) $author->id);
    $save->execute(['title' => 'Chemistry safety', 'subject_id' => makeSubject()->id], (int) $author->id);

    // A material with no subject belongs in every lesson, not none.
    expect(app(ListTeachingMaterialsAction::class)
        ->execute(['subject_id' => (int) $subject->id, 'include_general' => true])
        ->pluck('title')->all())
        ->toBe(['Arabic reader', 'Class rules handout']);
});

it('always lists an already attached material whatever the filters say', function () {
    $author = User::factory()->create();
    $hidden = app(SaveTeachingMaterialAction::class)
        ->execute(['title' => 'Chemistry safety', 'subject_id' => makeSubject()->id, 'tags' => 'lab'], (int) $author->id);

    // The picker syncs, so a hidden attachment is an attachment silently
    // deleted on the next save.
    $rows = app(ListTeachingMaterialsAction::class)->execute([
        'subject_id' => (int) makeSubject()->id,
        'tag' => 'nothing-matches-this',
        'include_ids' => [(int) $hidden->id],
    ]);

    expect($rows->pluck('title')->all())->toBe(['Chemistry safety']);
});

it('syncs the materials a lesson used rather than accumulating them', function () {
    $log = makeLessonLog();
    $teacherUserId = (int) $log->teacher->user_id;
    $save = app(SaveTeachingMaterialAction::class);
    $first = $save->execute(['title' => 'Worksheet'], $teacherUserId);
    $second = $save->execute(['title' => 'Poster'], $teacherUserId);
    $attach = app(AttachMaterialsToLessonAction::class);

    $attach->execute($log, [(int) $first->id, (int) $second->id], $teacherUserId);
    expect($log->teachingMaterials()->count())->toBe(2);

    // The register records what the lesson actually used, so unticking removes.
    $attach->execute($log, [(int) $second->id], $teacherUserId);
    expect($log->teachingMaterials()->pluck('teaching_materials.id')->all())
        ->toBe([(int) $second->id]);
});

it('ignores a material id that does not exist', function () {
    $log = makeLessonLog();
    $teacherUserId = (int) $log->teacher->user_id;
    $real = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Worksheet'], $teacherUserId);

    // A stale picker must not write a dangling link.
    $attached = app(AttachMaterialsToLessonAction::class)
        ->execute($log, [(int) $real->id, 999999], $teacherUserId);

    expect($attached)->toBe([(int) $real->id])
        ->and($log->teachingMaterials()->count())->toBe(1);
});

it('refuses to attach materials to somebody elses register', function () {
    $log = makeLessonLog();
    $stranger = makeTeacherRow();
    $material = app(SaveTeachingMaterialAction::class)
        ->execute(['title' => 'Worksheet'], (int) $stranger->user_id);

    // The same edit rule as the register itself, so a locked or someone else's
    // register cannot be edited sideways through the materials picker.
    app(AttachMaterialsToLessonAction::class)
        ->execute($log, [(int) $material->id], (int) $stranger->user_id);
})->throws(ValidationException::class);

it('lets an administrator attach on any register', function () {
    $log = makeLessonLog();
    $admin = User::factory()->create();
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Worksheet'], (int) $admin->id);

    app(AttachMaterialsToLessonAction::class)
        ->execute($log, [(int) $material->id], (int) $admin->id, true);

    expect($log->teachingMaterials()->count())->toBe(1);
});

it('leaves the legacy free-text materials column alone', function () {
    $log = makeLessonLog(['materials' => ['Textbook p.12']]);
    $teacherUserId = (int) $log->teacher->user_id;
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Worksheet'], $teacherUserId);

    app(AttachMaterialsToLessonAction::class)->execute($log, [(int) $material->id], $teacherUserId);

    // Rule 9: old registers keep their strings and keep displaying them.
    expect($log->refresh()->materials)->toBe(['Textbook p.12']);
});

it('drops the pivot rows when a material is deleted', function () {
    $log = makeLessonLog();
    $teacherUserId = (int) $log->teacher->user_id;
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Worksheet'], $teacherUserId);
    app(AttachMaterialsToLessonAction::class)->execute($log, [(int) $material->id], $teacherUserId);

    TeachingMaterial::query()->whereKey($material->id)->delete();

    expect($log->teachingMaterials()->count())->toBe(0);
});
