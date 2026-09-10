<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\AttachMaterialsToLessonAction;
use App\Domains\Academics\Actions\ListHomeworkForStudentAction;
use App\Domains\Academics\Actions\SaveTeachingMaterialAction;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * E13b — a material can go home with the homework.
 *
 * E13a records which materials a lesson *used*. That is not the same fact as
 * which ones a pupil needs at home: "whiteboard" stays in the room. These cover
 * the flag, what a family is shown, and what they are not.
 */
function homeworkMaterialSeed(array $logOverrides = []): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $log = makeLessonLog(array_merge([
        'year' => $year,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'homework' => 'Read page 12',
        'status' => LessonLogStatus::Submitted->value,
        'submitted_at' => now(),
    ], $logOverrides));

    $teacherUserId = (int) $log->teacher->user_id;
    $save = app(SaveTeachingMaterialAction::class);

    return [
        'student' => $student,
        'log' => $log,
        'teacherUserId' => $teacherUserId,
        'worksheet' => $save->execute(['title' => 'Alphabet worksheet', 'body' => 'Print double sided.'], $teacherUserId),
        'whiteboard' => $save->execute(['title' => 'Whiteboard'], $teacherUserId),
    ];
}

it('sends home only the materials the teacher chose', function () {
    $seed = homeworkMaterialSeed();

    app(AttachMaterialsToLessonAction::class)->execute(
        $seed['log'],
        [(int) $seed['worksheet']->id, (int) $seed['whiteboard']->id],
        $seed['teacherUserId'],
        false,
        [(int) $seed['worksheet']->id],
    );

    $row = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id)->firstOrFail();

    // A family needs the worksheet, not "whiteboard".
    expect($row['materials'])->toHaveCount(1)
        ->and($row['materials'][0]['title'])->toBe('Alphabet worksheet')
        ->and($row['materials'][0]['body'])->toBe('Print double sided.');
});

it('shows no materials when the teacher sent none home', function () {
    $seed = homeworkMaterialSeed();

    app(AttachMaterialsToLessonAction::class)->execute(
        $seed['log'],
        [(int) $seed['worksheet']->id],
        $seed['teacherUserId'],
    );

    // Attaching to the lesson is not the same as sending home. Defaulting the
    // other way would publish every register's materials to families at once.
    expect(app(ListHomeworkForStudentAction::class)
        ->execute((int) $seed['student']->id)->firstOrFail()['materials'])
        ->toBe([]);
});

it('stops sending a material home once it is unattached', function () {
    $seed = homeworkMaterialSeed();
    $attach = app(AttachMaterialsToLessonAction::class);
    $ids = [(int) $seed['worksheet']->id, (int) $seed['whiteboard']->id];

    $attach->execute($seed['log'], $ids, $seed['teacherUserId'], false, $ids);
    expect($seed['log']->homeworkMaterials()->count())->toBe(2);

    // Sending home a material the lesson no longer uses is not a state the
    // picker can produce, so the flag follows the attachment.
    $attach->execute($seed['log'], [(int) $seed['worksheet']->id], $seed['teacherUserId'], false, $ids);

    expect($seed['log']->homeworkMaterials()->pluck('teaching_materials.id')->all())
        ->toBe([(int) $seed['worksheet']->id]);
});

it('leaves the send-home choice alone when no homework ids are passed', function () {
    $seed = homeworkMaterialSeed();
    $attach = app(AttachMaterialsToLessonAction::class);
    $ids = [(int) $seed['worksheet']->id, (int) $seed['whiteboard']->id];

    $attach->execute($seed['log'], $ids, $seed['teacherUserId'], false, [(int) $seed['worksheet']->id]);

    // §5t: `public/build` is committed, so a bundle predating E13b is a real
    // client. It posts no homework key at all, and that must not un-send.
    $attach->execute($seed['log'], $ids, $seed['teacherUserId']);

    expect($seed['log']->homeworkMaterials()->pluck('teaching_materials.id')->all())
        ->toBe([(int) $seed['worksheet']->id]);
});

it('un-sends everything when an empty list is passed on purpose', function () {
    $seed = homeworkMaterialSeed();
    $attach = app(AttachMaterialsToLessonAction::class);
    $ids = [(int) $seed['worksheet']->id];

    $attach->execute($seed['log'], $ids, $seed['teacherUserId'], false, $ids);
    // Empty is a choice; absent is not. The two must not collapse.
    $attach->execute($seed['log'], $ids, $seed['teacherUserId'], false, []);

    expect($seed['log']->homeworkMaterials()->count())->toBe(0)
        ->and($seed['log']->teachingMaterials()->count())->toBe(1);
});

it('hides materials on a register that has not been submitted', function () {
    $seed = homeworkMaterialSeed(['status' => LessonLogStatus::Draft->value, 'submitted_at' => null]);

    app(AttachMaterialsToLessonAction::class)->execute(
        $seed['log'],
        [(int) $seed['worksheet']->id],
        $seed['teacherUserId'],
        false,
        [(int) $seed['worksheet']->id],
    );

    // A draft register is the teacher's working copy; the materials follow the
    // same rule as the homework text they belong to.
    expect(app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id))->toHaveCount(0);
});

it('walks the register picker and the family view over http', function () {
    $seed = homeworkMaterialSeed();
    $staff = User::query()->findOrFail($seed['log']->teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $staff->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->put(route('academics.registers.update', $seed['log']), [
            'taught_summary' => 'Letters',
            'homework' => 'Read page 12',
            'material_ids' => [(int) $seed['worksheet']->id, (int) $seed['whiteboard']->id],
            'homework_material_ids' => [(int) $seed['worksheet']->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.registers.show', $seed['log']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('homeworkMaterials', [(int) $seed['worksheet']->id])
            ->has('attachedMaterials', 2)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($seed['student']->user_id))
        ->get(route('portal.homework'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Homework')
            ->has('students.0.homework.0.materials', 1)
            ->where('students.0.homework.0.materials.0.title', 'Alphabet worksheet')
        );
});
