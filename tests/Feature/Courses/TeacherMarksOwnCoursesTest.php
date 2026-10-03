<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Support\Authorization\RoleGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * BACKLOG C16 slice N6 (STATUS §5ob). OWNER_ACTIONS 16, decided 2026-10-03:
 * "teachers mark only their own courses".
 *
 * The office links an instructor profile to a staff login and assigns it
 * courses on the course form; the teacher then opens Teacher review and
 * sees — and may mark — the submissions of those courses and no others.
 * The dean, with `courses.manage`, still sees the whole school.
 */
function ownCoursesRole(string $name): User
{
    $sets = RoleGrants::matrix();
    foreach ($sets[$name] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $role = Role::findOrCreate($name, 'web');
    $role->syncPermissions($sets[$name]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

function ownCoursesPublished(string $title): Course
{
    $dean = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => $title.' '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $dean->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

function ownCoursesSubmission(Course $course, string $studentName): ActivityAttempt
{
    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write about '.$course->title,
        'pattern' => 'teacher_marked',
        'max_score' => 5,
        'data' => ['prompt' => 'Write.', 'submission_kind' => 'written'],
    ]);
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => $studentName, 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, (int) $course->id, null);
    app(SubmitActivityAttemptAction::class)->execute((int) $activity->id, (int) $enrollment->id, (int) $enrollment->unified_student_id, (int) $course->id, ['text' => 'My answer.']);

    return ActivityAttempt::query()->where('activity_id', $activity->id)->firstOrFail();
}

it('shows a teacher the submissions of their own courses only, and lets them mark those alone', function () {
    $teacher = ownCoursesRole('teacher');
    expect($teacher->can('courses.review'))->toBeTrue()->and($teacher->can('courses.manage'))->toBeFalse();

    $mine = ownCoursesPublished('Mine');
    $theirs = ownCoursesPublished('Theirs');
    $mineAttempt = ownCoursesSubmission($mine, 'Aisha');
    $theirsAttempt = ownCoursesSubmission($theirs, 'Hassan');

    // No profile linked yet: an empty queue that says why, not a 403.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('catalog.reviews.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Reviews')
            ->has('rows', 0)->where('scope.own_courses', true)->where('scope.course_count', 0)
            ->has('t.reviews_scope_none'));

    // The office links the profile to the login and assigns one course.
    $profile = Instructor::query()->create(['name' => 'Ustaadh Mine', 'user_id' => $teacher->id]);
    $mine->instructors()->sync([$profile->id]);

    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('catalog.reviews.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)->where('rows.0.course_id', $mine->id)->where('rows.0.student_name', 'Aisha Pupil')
            ->where('pending_count', 1)
            ->where('scope.own_courses', true)->where('scope.course_count', 1)
            ->has('courses', 1)->where('courses.0.id', $mine->id));

    // The CSV is narrowed the same way.
    $csv = $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('catalog.reviews.export'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Aisha Pupil')->not->toContain('Hassan Pupil');

    // Marking another course's attempt by id is refused and writes nothing.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->from(route('catalog.reviews.index'))
        ->post(route('catalog.reviews.store'), ['kind' => 'activity', 'attempt_id' => $theirsAttempt->id, 'score' => 5, 'max_score' => 5, 'feedback' => 'Nice try'])
        ->assertRedirect(route('catalog.reviews.index'))->assertSessionHasErrors('attempt');
    expect($theirsAttempt->fresh()->status->value)->toBe('submitted');

    // Marking one's own is accepted.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('catalog.reviews.store'), ['kind' => 'activity', 'attempt_id' => $mineAttempt->id, 'score' => 4, 'max_score' => 5, 'feedback' => 'Well argued'])
        ->assertRedirect(route('catalog.reviews.index'))->assertSessionHasNoErrors();
    expect($mineAttempt->fresh()->status->value)->toBe('scored')
        ->and($mineAttempt->fresh()->score)->toBe(4)
        ->and($mineAttempt->fresh()->reviewed_by)->toBe($teacher->id);

    // The dean still sees the whole school, unscoped.
    $dean = ownCoursesRole('headmaster');
    $this->withoutLocalizationMiddleware()->actingAs($dean)->get(route('catalog.reviews.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.course_id', $theirs->id)
            ->where('scope.own_courses', false)->where('scope.course_count', null));
});

it('lets the office link a profile to a staff login once, and assign instructors on the course form', function () {
    $super = actingSystemAdmin();
    $teacher = ownCoursesRole('teacher');
    $other = ownCoursesRole('teacher');

    // The Instructors form offers the staff logins and saves the link.
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.instructors.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Instructors/Form')
            ->where('staff', fn ($staff) => collect($staff)->pluck('id')->contains($teacher->id)));
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.instructors.store'), ['name' => 'Ustaadh Link', 'user_id' => $teacher->id, 'is_active' => true])
        ->assertRedirect(route('admin.instructors.index'))->assertSessionHasNoErrors();
    $profile = Instructor::query()->where('name', 'Ustaadh Link')->sole();
    expect($profile->user_id)->toBe($teacher->id);

    // One login, one profile.
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.instructors.store'), ['name' => 'Ustaadh Twice', 'user_id' => $teacher->id])
        ->assertSessionHasErrors('user_id');
    // Editing the same profile with the same login is fine; clearing it clears it.
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.instructors.update', $profile), ['name' => 'Ustaadh Link', 'user_id' => $teacher->id])->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.instructors.update', $profile), ['name' => 'Ustaadh Link', 'user_id' => ''])->assertSessionHasNoErrors();
    expect($profile->fresh()->user_id)->toBeNull();
    $profile->forceFill(['user_id' => $other->id])->save();

    // The list says who the profile signs in as.
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.instructors.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('instructors.0.user_id', $other->id)->where('instructors.0.user_name', $other->name));

    // The CMS course form lists the instructors and syncs the assignment.
    $category = CourseCategory::query()->create(['name' => 'Tajweed', 'slug' => 'tajweed', 'order' => 1]);
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/CourseForm')
            ->where('instructors', fn ($rows) => collect($rows)->pluck('id')->contains($profile->id)));
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.store'), [
        'course_category_id' => $category->id, 'title' => 'Tajweed basics', 'short_desc' => 's', 'body' => '<p>b</p>',
        'language' => 'en', 'level' => 'all', 'status' => 'open', 'instructors' => [$profile->id],
    ])->assertRedirect(route('admin.courses.index'))->assertSessionHasNoErrors();
    $course = Course::query()->where('title', 'Tajweed basics')->sole();
    expect($course->instructors()->pluck('instructors.id')->all())->toBe([$profile->id]);

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.edit', $course))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('course.instructor_ids', [$profile->id]));

    // Unticked, the assignment goes; the review queue follows.
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.courses.update', $course), [
        'course_category_id' => $category->id, 'title' => 'Tajweed basics', 'slug' => $course->slug, 'short_desc' => 's', 'body' => '<p>b</p>',
        'language' => 'en', 'level' => 'all', 'status' => 'open',
    ])->assertRedirect(route('admin.courses.index'))->assertSessionHasNoErrors();
    expect($course->instructors()->count())->toBe(0);

    // The teacher's menu has the door; a teacher who may not review has not.
    $this->withoutLocalizationMiddleware()->actingAs($other)->get(route('catalog.reviews.index'))->assertOk();
    $outsider = User::factory()->create();
    $outsider->assignRole(Role::findOrCreate('teacher', 'web'));
    Role::findOrCreate('teacher', 'web')->revokePermissionTo('courses.review');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutLocalizationMiddleware()->actingAs($outsider->fresh())->get(route('catalog.reviews.index'))->assertForbidden();
});

it('says the phrases in three languages', function () {
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['instructors_field_user', 'instructors_user_hint', 'courses_instructors', 'courses_instructors_hint', 'reviews_scope_own', 'reviews_scope_none'] as $key) {
            expect(trans('admin.'.$key, [], $locale))->not->toBe('admin.'.$key);
        }
    }
});
