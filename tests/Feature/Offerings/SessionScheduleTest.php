<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows student and teacher schedules and can update plus bulk-mark attendance', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Schedule Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();

    $teacher = User::factory()->create();
    $studentUser = User::factory()->create();
    makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Mariam']);
    app(EnrollSelfLearningAction::class)->execute($studentUser->id, $course->id, $offering->id);

    $session = app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Morning live',
        'session_type' => 'live_online',
        'starts_at' => now()->addDay(),
        'teacher_user_id' => $teacher->id,
        'created_by' => $admin->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('catalog.offerings.sessions.update', [$offering->id, $session->id]), [
            'title' => 'Morning live updated',
            'session_type' => 'live_online',
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'teacher_user_id' => $teacher->id,
        ])
        ->assertRedirect(route('catalog.offerings.sessions.index', $offering->id));

    expect($session->fresh()->title)->toBe('Morning live updated');

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->get(route('learn.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Schedule')
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Morning live updated')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacher)
        ->get(route('teach.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Offerings/Teacher/Schedule')
            ->has('sessions', 1)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.offerings.sessions.attendance', [$offering->id, $session->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Offerings/Catalog/Attendance')
            ->where('roster.0.student_name', 'Mariam Ali')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.offerings.sessions.attendance.bulk', [$offering->id, $session->id]), [
            'status' => 'present',
            'attendance_mode' => 'online',
        ])
        ->assertRedirect(route('catalog.offerings.sessions.attendance', [$offering->id, $session->id]));

    $marked = AttendanceRecord::query()->first();
    expect(AttendanceRecord::query()->count())->toBe(1)
        ->and($marked?->status)->toBe(\App\Domains\Offerings\Enums\AttendanceStatus::Present);
});
