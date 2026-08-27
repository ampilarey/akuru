<?php

use App\Domains\Courses\Components\Quran\Actions\SyncHifzMilestoneProgressAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Offerings\Actions\BackfillHalaqaStructureAction;
use App\Domains\Offerings\Actions\VerifyHalaqaStructureAction;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingHalaqaEnrollmentLink;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedHifzStructureFixture(): array
{
    $year = makeYear();
    $student = makeStudent();
    $teacher = makeTeacherRow();
    $program = HifzProgram::query()->create([
        'name' => 'Halaqa Al-Falah',
        'academic_year_id' => $year->id,
        'status' => 'active',
    ]);
    $enrollment = HifzEnrollment::query()->create([
        'hifz_program_id' => $program->id,
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'start_date' => '2026-01-10',
        'status' => 'active',
    ]);
    $session = HifzSession::query()->create([
        'hifz_program_id' => $program->id,
        'teacher_id' => $teacher->id,
        'session_date' => '2026-02-01',
        'title' => 'Morning halaqa',
        'status' => 'draft',
    ]);
    $record = HifzSessionRecord::query()->create([
        'hifz_session_id' => $session->id,
        'hifz_program_id' => $program->id,
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'attendance_status' => 'late',
    ]);
    $milestones = collect(['approved', 'pending'])->map(fn (string $status) => HifzMilestone::query()->create([
        'hifz_program_id' => $program->id,
        'student_id' => $student->id,
        'type' => 'juz_completed',
        'juz_number' => $status === 'approved' ? 30 : 29,
        'title' => 'Juz',
        'completed_at' => now(),
        'status' => $status,
    ]));

    return compact('year', 'student', 'teacher', 'program', 'enrollment', 'session', 'record', 'milestones');
}

it('backfills the full structure idempotently and the verify gate flips red to green', function () {
    $ctx = seedHifzStructureFixture();

    $before = app(VerifyHalaqaStructureAction::class)->execute();
    expect($before['ok'])->toBeFalse()
        ->and($before['unmapped_programs'])->toHaveCount(1);
    $this->artisan('halaqa:verify-structure')->assertFailed();

    $report = app(BackfillHalaqaStructureAction::class)->execute();
    expect($report['programs_mapped'])->toBe(1)
        ->and($report['sessions_mirrored'])->toBe(1)
        ->and($report['enrollments_linked'])->toBe(1)
        ->and($report['attendance_written'])->toBe(1);

    $this->artisan('halaqa:verify-structure')->assertSuccessful();

    $link = OfferingHalaqaLink::query()->where('hifz_program_id', $ctx['program']->id)->firstOrFail();
    $course = Course::query()->findOrFail($link->offering->course_id);
    expect($course->course_type)->toBe('hifz')
        ->and($link->dual_write)->toBeFalse()
        ->and((int) $link->offering->academic_year_id)->toBe($ctx['year']->id);

    $enrollmentLink = OfferingHalaqaEnrollmentLink::query()
        ->where('hifz_enrollment_id', $ctx['enrollment']->id)
        ->firstOrFail();
    $engineEnrollment = CourseEnrollment::query()->findOrFail($enrollmentLink->course_enrollment_id);
    expect((int) $engineEnrollment->unified_student_id)->toBe($ctx['student']->id)
        ->and((int) $engineEnrollment->course_offering_id)->toBe((int) $link->course_offering_id);

    $sessionLink = OfferingHalaqaSessionLink::query()
        ->where('hifz_session_id', $ctx['session']->id)
        ->firstOrFail();
    $attendance = AttendanceRecord::query()
        ->where('course_offering_session_id', $sessionLink->course_offering_session_id)
        ->where('enrollment_id', $engineEnrollment->id)
        ->firstOrFail();
    expect($attendance->status->value)->toBe('late');

    // Idempotent: a second run creates nothing.
    $again = app(BackfillHalaqaStructureAction::class)->execute();
    expect($again['programs_mapped'])->toBe(0)
        ->and($again['sessions_mirrored'])->toBe(0)
        ->and($again['enrollments_linked'])->toBe(0)
        ->and($again['attendance_written'])->toBe(0);
});

it('maps milestones onto completion through the evaluator contract', function () {
    $ctx = seedHifzStructureFixture();
    app(BackfillHalaqaStructureAction::class)->execute();

    $sync = app(SyncHifzMilestoneProgressAction::class)->execute($ctx['program']->id);
    expect($sync['evaluated'])->toBe(1)->and($sync['completed'])->toBe(0);

    $enrollmentLink = OfferingHalaqaEnrollmentLink::query()
        ->where('hifz_enrollment_id', $ctx['enrollment']->id)
        ->firstOrFail();
    $engineEnrollment = CourseEnrollment::query()->findOrFail($enrollmentLink->course_enrollment_id);
    expect((int) $engineEnrollment->progress_percentage)->toBe(50)
        ->and($engineEnrollment->completed_at)->toBeNull();

    // Approving the second milestone completes the enrollment on next sync.
    $ctx['milestones'][1]->update(['status' => 'approved']);
    $sync = app(SyncHifzMilestoneProgressAction::class)->execute($ctx['program']->id);
    expect($sync['completed'])->toBe(1);

    $engineEnrollment->refresh();
    expect((int) $engineEnrollment->progress_percentage)->toBe(100)
        ->and($engineEnrollment->status)->toBe('completed')
        ->and($engineEnrollment->completed_at)->not->toBeNull();
});

it('respects an existing hand-made link and reports unlinked students without guessing', function () {
    $ctx = seedHifzStructureFixture();

    // A second program already linked by hand through the Sessions picker.
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = Course::query()->create([
        'course_category_id' => CourseCategory::query()->create([
            'name' => 'General', 'slug' => 'general', 'order' => 0,
        ])->id,
        'title' => 'Hand made',
        'slug' => 'hand-made',
        'short_desc' => 'x',
        'body' => 'x',
        'cover_image' => '',
        'language' => 'en',
        'status' => 'closed',
        'course_type' => 'general',
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Hand made offering',
        'slug' => 'hand-made-offering',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'created_by' => $admin->id,
    ]);
    $manualProgram = HifzProgram::query()->create(['name' => 'Manual halaqa', 'status' => 'active']);
    OfferingHalaqaLink::query()->create([
        'course_offering_id' => $offering->id,
        'hifz_program_id' => $manualProgram->id,
        'dual_write' => false,
    ]);

    $report = app(BackfillHalaqaStructureAction::class)->execute();
    // Only the unmapped program creates a course; the manual link is respected.
    expect($report['programs_mapped'])->toBe(1)
        ->and(OfferingHalaqaLink::query()->count())->toBe(2)
        ->and(Course::query()->where('course_type', 'hifz')->count())->toBe(1);

    // A session record for a student with no active enrollment stays unwritten
    // and is not guessed at — the verify gate stays green because the reader
    // exposes no enrollment to expect attendance for.
    $stranger = makeStudent();
    HifzSessionRecord::query()->create([
        'hifz_session_id' => $ctx['session']->id,
        'hifz_program_id' => $ctx['program']->id,
        'student_id' => $stranger->id,
        'teacher_id' => $ctx['teacher']->id,
        'attendance_status' => 'present',
    ]);
    $again = app(BackfillHalaqaStructureAction::class)->execute();
    expect($again['attendance_written'])->toBe(0)
        ->and(app(VerifyHalaqaStructureAction::class)->execute()['ok'])->toBeTrue();
});
