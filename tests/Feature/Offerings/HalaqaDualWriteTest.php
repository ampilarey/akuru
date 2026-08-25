<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Services\HifzSessionService;
use App\Domains\Offerings\Actions\SaveOfferingHalaqaLinkAction;
use App\Domains\Offerings\Actions\SyncHalaqaDualWriteAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function seedHalaqaDualWriteFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Halaqa dual-write',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Mapped halaqa',
        'slug' => 'mapped-halaqa-dual',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'created_by' => $admin->id,
    ]);
    $program = HifzProgram::query()->create([
        'name' => 'Dual-write halaqa',
        'status' => 'active',
    ]);
    $teacher = makeTeacherRow();
    $hifzSession = HifzSession::query()->create([
        'hifz_program_id' => $program->id,
        'teacher_id' => $teacher->id,
        'session_date' => now()->subDay()->toDateString(),
        'title' => 'Unmapped Hifz session',
        'status' => 'draft',
    ]);
    $student = makeStudent(['first_name' => 'Yusuf']);
    HifzEnrollment::query()->create([
        'hifz_program_id' => $program->id,
        'student_id' => $student->id,
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
    app(SaveOfferingHalaqaLinkAction::class)->execute([
        'course_offering_id' => $offering->id,
        'hifz_program_id' => $program->id,
    ]);

    return compact('admin', 'course', 'offering', 'program', 'teacher', 'hifzSession', 'student');
}

it('refuses dual-write sync while the flag is off', function () {
    $fixture = seedHalaqaDualWriteFixture();

    expect(fn () => app(SyncHalaqaDualWriteAction::class)->execute($fixture['offering']->id))
        ->toThrow(ValidationException::class);

    expect(CourseOfferingSession::query()->where('course_offering_id', $fixture['offering']->id)->count())->toBe(0)
        ->and(CourseEnrollment::query()->where('course_offering_id', $fixture['offering']->id)->count())->toBe(0)
        ->and(HifzSession::query()->count())->toBe(1);
});

it('mirrors unmapped hifz sessions and enrollments onto the offering', function () {
    config(['quran.halaqa_dual_write' => true]);
    $fixture = seedHalaqaDualWriteFixture();

    $result = app(SyncHalaqaDualWriteAction::class)->execute($fixture['offering']->id);

    expect($result['sessions_created'])->toBe(1)
        ->and($result['enrollments_mirrored'])->toBe(1)
        ->and(CourseOfferingSession::query()->where('course_offering_id', $fixture['offering']->id)->count())->toBe(1)
        ->and(OfferingHalaqaSessionLink::query()->where('hifz_session_id', $fixture['hifzSession']->id)->exists())->toBeTrue()
        ->and(CourseEnrollment::query()
            ->where('course_offering_id', $fixture['offering']->id)
            ->where('unified_student_id', $fixture['student']->id)
            ->exists())->toBeTrue()
        ->and(HifzSession::query()->count())->toBe(1);

    $again = app(SyncHalaqaDualWriteAction::class)->execute($fixture['offering']->id);
    expect($again['sessions_created'])->toBe(0)
        ->and(CourseOfferingSession::query()->where('course_offering_id', $fixture['offering']->id)->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($fixture['admin'])
        ->post(route('catalog.offerings.halaqa.sync', $fixture['offering']->id))
        ->assertRedirect(route('catalog.offerings.sessions.index', $fixture['offering']->id));

    expect(OfferingHalaqaLink::query()->where('course_offering_id', $fixture['offering']->id)->value('dual_write'))->toBeTrue();
});

it('does not change hifz session create when dual-write is off', function () {
    $fixture = seedHalaqaDualWriteFixture();

    app(HifzSessionService::class)->createSessionForToday(
        $fixture['program'],
        $fixture['teacher'],
        $fixture['admin'],
    );

    expect(HifzSession::query()->count())->toBe(2)
        ->and(CourseOfferingSession::query()->where('course_offering_id', $fixture['offering']->id)->count())->toBe(0);
});

it('mirrors a new hifz session after catalog sync has enabled the link', function () {
    config(['quran.halaqa_dual_write' => true]);
    $fixture = seedHalaqaDualWriteFixture();
    app(SyncHalaqaDualWriteAction::class)->execute($fixture['offering']->id);

    app(HifzSessionService::class)->createSessionForToday(
        $fixture['program'],
        $fixture['teacher'],
        $fixture['admin'],
    );

    expect(HifzSession::query()->count())->toBe(2)
        ->and(CourseOfferingSession::query()->where('course_offering_id', $fixture['offering']->id)->count())->toBe(2);
});
