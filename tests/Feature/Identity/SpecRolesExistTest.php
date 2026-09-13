<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * SPEC §8 "User Roles" names seven. Six existed.
 *
 * **§8.3 Course Creator did not exist at all.** The section lists nine things
 * it manages and one rule — "Course creators should **not publish courses
 * directly** unless permission is granted". The rule was enforceable
 * (`TransitionCourseWorkflowAction` refuses `Published` without
 * `courses.publish`) but no role held `courses.manage` *without*
 * `courses.publish`. To let somebody build a course you had to make them
 * `admin` (108 permissions, publish included) or `headmaster` (79). The one
 * shape §8.3 asks for was not available.
 *
 * **§8.4 Dean / Supervisor existed and could do none of its eight duties.** It
 * held 33 permissions, **none beginning `courses.`**, and the `/catalog` route
 * group did not list it — so it failed twice over and every §8.4 duty answered
 * 403.
 *
 * That is not only a role-table problem. PR #316 put §35's approve / reject /
 * request-changes control on `/catalog/courses` — a screen the supervisor
 * could not open. The feature was reachable by everyone except the role §35 is
 * named after.
 */
uses(RefreshDatabase::class);

function roleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

it('creates §8.3 and §8.4 by migration, not by seeder', function () {
    // This suite runs migrations and no seeders, which is exactly what
    // `scripts/pull-deploy-test.sh` does on a deployment — so a role that only
    // a seeder creates does not reach an existing install. That is the argument
    // `2026_09_10_000010_seeder_only_route_permissions` already made for
    // permissions, and it holds for roles.
    //
    // §8.4 is one heading covering "Dean / Supervisor", and `supervisor` is the
    // name already in the database, the seeder and the route middleware — a
    // separate `dean` role would be two names for one job.
    $migrated = Role::query()->pluck('name')->all();

    expect($migrated)->toContain('course_creator');
    expect($migrated)->toContain('supervisor');

    // Recorded rather than asserted (rule 1): `admin`, `teacher`, `student` and
    // `parent` are still seeder-only and so are absent here. That is the same
    // defect class and its own slice — see STATUS and KNOWN_ISSUES item 11. Pinning
    // it now would fail on a truth this slice is not fixing.
    expect(array_diff(['admin', 'teacher', 'student', 'parent'], $migrated))->not->toBeEmpty(
        'These §8 roles are now created by a migration. Delete this expectation '
        .'and assert all seven roles instead — KNOWN_ISSUES item 11 is fixed.'
    );
});

it('lets a course creator build but not publish, which is §8.3 rule', function () {
    $creator = roleUser('course_creator');

    expect($creator->can('courses.manage'))->toBeTrue();
    // §8.3: "Course creators should not publish courses directly unless
    // permission is granted." Expressed as the absence of a permission rather
    // than a special case in code.
    expect($creator->can('courses.publish'))->toBeFalse();

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Creator draft '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $creator->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, false);

    // The engine already refused; there was simply no role shaped to meet it.
    expect(fn () => app(TransitionCourseWorkflowAction::class)
        ->execute($course->fresh(), CourseWorkflowStatus::Published, $creator->can('courses.publish')))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('grants a course creator publish rights when an admin adds the permission', function () {
    // "unless permission is granted" — it has to actually work.
    $creator = roleUser('course_creator');
    $creator->givePermissionTo('courses.publish');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $creator = $creator->fresh();

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Creator publishes '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $creator->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, false);
    app(TransitionCourseWorkflowAction::class)
        ->execute($course->fresh(), CourseWorkflowStatus::Published, $creator->can('courses.publish'));

    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Published);
});

it('lets a supervisor reach the screens §8.4 says are its job', function () {
    // All four answered 403 before: no `courses.` permission, and the role was
    // absent from the /catalog route group.
    $supervisor = roleUser('supervisor');

    foreach (['/catalog/courses', '/catalog/reviews', '/catalog/reports', '/catalog/offerings'] as $uri) {
        $this->actingAs($supervisor)
            ->withoutLocalizationMiddleware()
            ->get($uri)
            ->assertOk();
    }
});

it('lets a supervisor approve, which §8.4 requires and publishing enforces', function () {
    // §8.4's "Approve courses" *is* publishing: an approval moves the course to
    // Published, and RecordCourseReviewDecisionAction refuses that without
    // `courses.publish`. A supervisor who may approve but may not publish
    // could approve nothing.
    $supervisor = roleUser('supervisor');

    expect($supervisor->can('courses.manage'))->toBeTrue();
    expect($supervisor->can('courses.publish'))->toBeTrue();

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'For review '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $supervisor->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);

    $this->actingAs($supervisor)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', ['decision' => 'approved'])
        ->assertRedirect();

    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Published);
});

it('refuses a course creator the review decisions §8.4 gives the supervisor', function () {
    // The browser walk for this slice surfaced this: adding `course_creator` to
    // the catalog group had quietly handed them Reject and Request changes,
    // because those land in `draft` and need no publish right. §8.3's creator
    // does not review at all — not even somebody else's course.
    $creator = roleUser('course_creator');
    $supervisor = roleUser('supervisor');

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Not yours to judge '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $supervisor->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);

    $this->actingAs($creator)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', [
            'decision' => 'changes_requested',
            'comment' => 'Sending back a course that is not mine.',
        ])
        ->assertForbidden();

    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::InReview);
});

it('still refuses the catalog to a role §8 does not put there', function () {
    // Widening the group must not open it to everyone.
    foreach (['student', 'parent'] as $role) {
        $this->actingAs(roleUser($role))
            ->withoutLocalizationMiddleware()
            ->get('/catalog/courses')
            ->assertForbidden();
    }
});
