<?php

use App\Domains\Courses\Actions\AuthorizeLessonAccessAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolveEnrollmentAccessWindowAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §11.7 lists ten fields `course_enrollments` should include:
 *
 *   > Course ID · Course offering ID nullable · Student ID · Enrollment type ·
 *   > Status · **Access starts at nullable** · **Access ends at nullable** ·
 *   > Completed at nullable · Progress percentage · Certificate issued at
 *   > nullable
 *
 * Eight were there. **"Access starts at" and "Access ends at" existed
 * nowhere**, so access to a course had no time dimension at all:
 * `AuthorizeLessonAccessAction` gated on enrolment status and §26's unlock
 * rules and nothing else. An enrolment could not begin later and could not run
 * out.
 *
 * The offering's own `starts_at` / `ends_at` cannot stand in — those are the
 * cohort's dates. §11.7 puts the window on the **enrolment**, because a
 * student who joins late, transfers in, or whose paid access is for a fixed
 * term has a window of their own.
 *
 * **The tenth field is deliberately absent and stays absent.**
 * "Certificate issued at" already lives on `issued_certificates`, which
 * carries `enrollment_id`, `issued_at` and `revoked_at`. A copy here would be
 * a second source of truth (rule 11), and it is the copy that would rot: a
 * revoked certificate would leave a stale issue date on the enrolment.
 */
uses(RefreshDatabase::class);

function windowLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Window '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $module = CourseModule::query()->create([
        'course_id' => $course->id, 'title' => 'Unit', 'position' => 1, 'status' => 'draft',
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_id' => $course->id, 'course_module_id' => $module->id, 'title' => 'Lesson one',
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text', 'data' => ['body' => 'Hello'],
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh());

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Window', 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, null);

    return compact('admin', 'course', 'lesson', 'user', 'enrollment');
}

it('adds the two §11.7 fields that existed nowhere', function () {
    expect(Schema::hasColumn('course_enrollments', 'access_starts_at'))->toBeTrue();
    expect(Schema::hasColumn('course_enrollments', 'access_ends_at'))->toBeTrue();

    // Deliberately still absent — `issued_certificates` owns it.
    expect(Schema::hasColumn('course_enrollments', 'certificate_issued_at'))->toBeFalse();
    expect(Schema::hasColumn('issued_certificates', 'enrollment_id'))->toBeTrue();
    expect(Schema::hasColumn('issued_certificates', 'issued_at'))->toBeTrue();
});

it('treats an absent window as unbounded, which every existing row is', function () {
    ['user' => $user, 'lesson' => $lesson, 'enrollment' => $enrollment] = windowLesson();

    expect($enrollment->access_starts_at)->toBeNull();
    expect($enrollment->access_ends_at)->toBeNull();

    $access = app(AuthorizeLessonAccessAction::class)->execute($lesson->id, $user);
    expect($access['via'])->toBe('enrollment');
});

it('refuses a lesson before access starts, and says so', function () {
    ['user' => $user, 'lesson' => $lesson, 'enrollment' => $enrollment] = windowLesson();
    $enrollment->update(['access_starts_at' => now()->addWeek()]);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get('/learn/lessons/'.$lesson->id)
        ->assertForbidden();

    // "Not yet" and "expired" lead to different actions — wait, or go and pay.
    $window = app(ResolveEnrollmentAccessWindowAction::class)->execute($enrollment->fresh());
    expect($window['open'])->toBeFalse();
    expect($window['reason'])->toBe(ResolveEnrollmentAccessWindowAction::PENDING);
    expect($window['message'])->toContain('starts on');
});

it('refuses a lesson after access ends, and says which end failed', function () {
    ['user' => $user, 'lesson' => $lesson, 'enrollment' => $enrollment] = windowLesson();
    $enrollment->update(['access_ends_at' => now()->subDay()]);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get('/learn/lessons/'.$lesson->id)
        ->assertForbidden();

    $window = app(ResolveEnrollmentAccessWindowAction::class)->execute($enrollment->fresh());
    expect($window['reason'])->toBe(ResolveEnrollmentAccessWindowAction::EXPIRED);
    expect($window['message'])->toContain('ended on');
});

it('lets a student in while the window is open at both ends', function () {
    ['user' => $user, 'lesson' => $lesson, 'enrollment' => $enrollment] = windowLesson();
    $enrollment->update([
        'access_starts_at' => now()->subDay(),
        'access_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get('/learn/lessons/'.$lesson->id)
        ->assertOk();
});

it('never shuts staff out of a lesson', function () {
    // Staff reach lessons via `courses.manage`, before any enrolment is looked
    // for. A window on one student's enrolment must not close the author's
    // preview.
    ['admin' => $admin, 'lesson' => $lesson, 'enrollment' => $enrollment] = windowLesson();
    $enrollment->update(['access_ends_at' => now()->subYear()]);

    expect(app(AuthorizeLessonAccessAction::class)->execute($lesson->id, $admin)['via'])->toBe('staff');
});

it('refuses a window that ends before it starts', function () {
    // Not a narrow window — one nothing can ever satisfy. A student locked out
    // by a typo cannot tell that from a deliberate block.
    app(ResolveEnrollmentAccessWindowAction::class)->validated([
        'access_starts_at' => '2026-10-01 08:00',
        'access_ends_at' => '2026-09-01 08:00',
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);

it('saves and clears the window over HTTP', function () {
    ['enrollment' => $enrollment] = windowLesson();
    $admin = actingPeopleAdmin();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin->fresh())
        ->withoutLocalizationMiddleware()
        ->patch('/admin/enrollments/'.$enrollment->id.'/access-window', [
            'access_starts_at' => '2026-10-01 08:00',
            'access_ends_at' => '2026-12-01 17:00',
        ])
        ->assertRedirect();

    expect($enrollment->fresh()->access_starts_at)->not->toBeNull();

    // Blank means unbounded, so clearing has to be a real operation rather
    // than a no-op that leaves the old dates in place.
    $this->actingAs($admin->fresh())
        ->withoutLocalizationMiddleware()
        ->patch('/admin/enrollments/'.$enrollment->id.'/access-window', [
            'access_starts_at' => '',
            'access_ends_at' => '',
        ])
        ->assertRedirect();

    expect($enrollment->fresh()->access_starts_at)->toBeNull();
    expect($enrollment->fresh()->access_ends_at)->toBeNull();
});
