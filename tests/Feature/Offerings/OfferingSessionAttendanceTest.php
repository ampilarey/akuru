<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\RecordOfferingAttendanceAction;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use App\Domains\Offerings\Enums\AttendanceStatus;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishOfferingCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Session Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();

    return compact('admin', 'course', 'offering');
}

it('creates offering sessions and marks attendance only for that offering roster', function () {
    ['admin' => $admin, 'course' => $course, 'offering' => $offering] = publishOfferingCourse();

    $other = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Evening live',
        'delivery_mode' => 'live_online',
        'status' => 'open',
        'created_by' => $admin->id,
    ]);

    $onOffering = User::factory()->create();
    makeStudent(['user_id' => $onOffering->id, 'first_name' => 'On']);
    $offOffering = User::factory()->create();
    makeStudent(['user_id' => $offOffering->id, 'first_name' => 'Off']);

    $enrolled = app(EnrollSelfLearningAction::class)->execute($onOffering->id, $course->id, $offering->id);
    $otherEnrollment = app(EnrollSelfLearningAction::class)->execute($offOffering->id, $course->id, $other->id);

    $session = app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Week 1 live',
        'session_type' => 'live_online',
        'starts_at' => now()->addDay(),
        'location_name' => 'Studio A',
        'created_by' => $admin->id,
    ]);

    expect($session->timezone)->toBe('Indian/Maldives')
        ->and($session->academic_year_id)->toBe($offering->academic_year_id);

    $row = app(RecordOfferingAttendanceAction::class)->execute([
        'course_offering_session_id' => $session->id,
        'enrollment_id' => $enrolled->id,
        'status' => 'present',
        'attendance_mode' => 'online',
        'marked_by' => $admin->id,
    ]);

    expect($row->status)->toBe(AttendanceStatus::Present)
        ->and($row->student_id)->toBe((int) $enrolled->unified_student_id)
        ->and($row->course_offering_id)->toBe($offering->id);

    expect(fn () => app(RecordOfferingAttendanceAction::class)->execute([
        'course_offering_session_id' => $session->id,
        'enrollment_id' => $otherEnrollment->id,
        'status' => 'present',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(RecordOfferingAttendanceAction::class)->execute([
        'course_offering_session_id' => $session->id,
        'enrollment_id' => $enrolled->id,
        'status' => 'maybe',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Bad type',
        'session_type' => 'correspondence',
        'starts_at' => now()->addDays(2),
    ]))->toThrow(ValidationException::class);

    expect(CourseOfferingSession::query()->count())->toBe(1)
        ->and(AttendanceRecord::query()->count())->toBe(1);
});

it('renders session and attendance screens and exports csv', function () {
    ['admin' => $admin, 'course' => $course, 'offering' => $offering] = publishOfferingCourse();
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Yusuf']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, $offering->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.offerings.sessions.store', $offering), [
            'title' => 'Orientation',
            'session_type' => 'orientation',
            'starts_at' => now()->addHours(3)->toDateTimeString(),
            'location_name' => 'Hall 1',
            'is_required' => true,
        ])
        ->assertRedirect(route('catalog.offerings.sessions.index', $offering));

    $session = CourseOfferingSession::query()->firstOrFail();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.offerings.sessions.index', $offering))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Offerings/Catalog/Sessions')
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Orientation'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.offerings.sessions.attendance', [$offering, $session]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Offerings/Catalog/Attendance')
            ->has('roster', 1)
            ->where('roster.0.enrollment_id', $enrollment->id)
            ->where('roster.0.status', 'pending'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.offerings.sessions.attendance.mark', [$offering, $session]), [
            'enrollment_id' => $enrollment->id,
            'status' => 'late',
            'attendance_mode' => 'physical',
        ])
        ->assertRedirect(route('catalog.offerings.sessions.attendance', [$offering, $session]));

    expect(AttendanceRecord::query()->value('status'))->toBe(AttendanceStatus::Late->value);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.offerings.sessions.export', $offering));
    $csv->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain('Orientation');
});

it('shows upcoming sessions on the student dashboard and course page', function () {
    ['course' => $course, 'offering' => $offering] = publishOfferingCourse();
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Aisha']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, $offering->id);

    app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Tomorrow live',
        'session_type' => 'workshop',
        'starts_at' => now()->addDay(),
        'location_name' => 'Room 2',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Dashboard')
            ->has('upcoming_sessions', 1)
            ->where('upcoming_sessions.0.title', 'Tomorrow live'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.courses.show', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Show')
            ->has('upcoming_sessions', 1)
            ->where('upcoming_sessions.0.title', 'Tomorrow live'));
});
