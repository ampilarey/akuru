<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Promotion is the most destructive operation in the product — it moves every
 * active pupil from one academic year into the next — and had **no route-level
 * test at all**. Found by scanning for mutating routes that no test exercises
 * by name or by path; 185 came back, and this was the one worth covering first.
 *
 * The dry-run gate is real and lives in `PromoteStudentsAction` rather than the
 * controller, which is correct (rule 5). These tests exist so it cannot be
 * refactored away silently: without it, one POST promotes a whole school with
 * nobody having seen a preview.
 */
function promotionSeed(): array
{
    $source = makeYear(['name' => '2025-2026', 'is_current' => true, 'status' => 'active']);
    $target = makeYear(['name' => '2026-2027', 'status' => 'upcoming']);
    $from = makeClass($source, 'Grade 5', 'A');
    $to = makeClass($target, 'Grade 6', 'A');
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($from, (int) $student->id);

    return compact('source', 'target', 'from', 'to', 'student');
}

function promotionAdmin(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    return $user;
}

it('refuses to commit a promotion nobody has previewed', function () {
    $seed = promotionSeed();

    // The whole safety story: without a dry run, one POST moves a school.
    $this->withoutLocalizationMiddleware()
        ->actingAs(promotionAdmin())
        ->post(route('academics.promotion.commit'), [
            'source_year_id' => $seed['source']->id,
            'target_year_id' => $seed['target']->id,
            'class_map' => [$seed['from']->id => $seed['to']->id],
        ])
        ->assertServerError();

    // Nothing moved.
    expect(ClassStudent::query()->where('academic_year_id', $seed['target']->id)->count())->toBe(0);
});

it('previews without moving anybody', function () {
    $seed = promotionSeed();

    $this->withoutLocalizationMiddleware()
        ->actingAs(promotionAdmin())
        ->post(route('academics.promotion.dry-run'), [
            'source_year_id' => $seed['source']->id,
            'target_year_id' => $seed['target']->id,
            'class_map' => [$seed['from']->id => $seed['to']->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('promotion_report');

    expect(ClassStudent::query()->where('academic_year_id', $seed['target']->id)->count())->toBe(0);
});

it('commits once a dry run has been done', function () {
    $seed = promotionSeed();
    $admin = promotionAdmin();
    $payload = [
        'source_year_id' => $seed['source']->id,
        'target_year_id' => $seed['target']->id,
        'class_map' => [$seed['from']->id => $seed['to']->id],
    ];

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.dry-run'), $payload)->assertRedirect();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.commit'), $payload)
        ->assertRedirect()
        ->assertSessionHas('promotion_report');

    $moved = ClassStudent::query()
        ->where('academic_year_id', $seed['target']->id)
        ->where('student_id', $seed['student']->id)
        ->first();

    expect($moved)->not->toBeNull()
        ->and((int) $moved->class_id)->toBe((int) $seed['to']->id);
});

it('spends the dry run, so a second commit is refused', function () {
    $seed = promotionSeed();
    $admin = promotionAdmin();
    $payload = [
        'source_year_id' => $seed['source']->id,
        'target_year_id' => $seed['target']->id,
        'class_map' => [$seed['from']->id => $seed['to']->id],
    ];

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.dry-run'), $payload)->assertRedirect();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.commit'), $payload)->assertRedirect();

    // The confirmation is pulled from cache, not merely read — so an accidental
    // double submit cannot promote twice.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.commit'), $payload)
        ->assertServerError();
});

it('refuses a promotion into the same year it came from', function () {
    $seed = promotionSeed();

    $this->withoutLocalizationMiddleware()
        ->actingAs(promotionAdmin())
        ->post(route('academics.promotion.dry-run'), [
            'source_year_id' => $seed['source']->id,
            'target_year_id' => $seed['source']->id,
        ])
        ->assertSessionHasErrors('target_year_id');
});

it('keeps a teacher out of the promotion wizard entirely', function () {
    $teacher = User::factory()->create();
    Role::findOrCreate('teacher', 'web');
    $teacher->assignRole('teacher');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacher)
        ->get(route('academics.promotion.create'))
        ->assertForbidden();
});

it('leaves a repeating pupil on their existing roster row', function () {
    $seed = promotionSeed();
    $admin = promotionAdmin();
    $payload = [
        'source_year_id' => $seed['source']->id,
        'target_year_id' => $seed['target']->id,
        'class_map' => [$seed['from']->id => $seed['to']->id],
        'overrides' => [$seed['student']->id => 'repeat'],
    ];

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.dry-run'), $payload)->assertRedirect();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.commit'), $payload)->assertRedirect();

    // Pinning what the code actually does: `repeat()` touches the row and
    // leaves it where it is — same class, same year, still active. No row is
    // created in the target year.
    //
    // ⚠ Worth an owner decision rather than a silent assumption: this means a
    // repeating pupil holds a roster row whose `academic_year_id` is the *old*
    // year, so anything scoped to the current year — attendance, homework, the
    // absence list — will not see them until somebody re-assigns them by hand.
    // That may be intended (the class is re-created and they are re-enrolled)
    // or it may be a gap. The test records the behaviour; it does not bless it.
    expect(ClassStudent::query()->where('academic_year_id', $seed['target']->id)->count())->toBe(0);

    $stayed = ClassStudent::query()
        ->where('student_id', $seed['student']->id)
        ->sole();

    expect((int) $stayed->class_id)->toBe((int) $seed['from']->id)
        ->and((int) $stayed->academic_year_id)->toBe((int) $seed['source']->id)
        ->and($stayed->status->value)->toBe(ClassStudentStatus::Active->value);
});

it('does not carry a pupil who has already left the school', function () {
    $seed = promotionSeed();
    ClassStudent::query()
        ->where('student_id', $seed['student']->id)
        ->update(['status' => ClassStudentStatus::Left->value]);

    $admin = promotionAdmin();
    $payload = [
        'source_year_id' => $seed['source']->id,
        'target_year_id' => $seed['target']->id,
        'class_map' => [$seed['from']->id => $seed['to']->id],
    ];

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.dry-run'), $payload)->assertRedirect();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.promotion.commit'), $payload)->assertRedirect();

    // Only active roster rows move; a transferred pupil is not the new year's
    // problem.
    expect(ClassStudent::query()->where('academic_year_id', $seed['target']->id)->count())->toBe(0);
});
