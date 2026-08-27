<?php

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\BackfillHalaqaStructureAction;
use App\Domains\Offerings\Models\OfferingHalaqaEnrollmentLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedMilestoneFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $student = makeStudent(['first_name' => 'Yusuf']);
    $teacher = makeTeacherRow();
    $teacherUser = User::query()->findOrFail($teacher->user_id);
    $program = HifzProgram::query()->create([
        'name' => 'Halaqa An-Noor',
        'academic_year_id' => $year->id,
        'status' => 'active',
    ]);
    HifzEnrollment::query()->create([
        'hifz_program_id' => $program->id,
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'start_date' => '2026-01-10',
        'status' => 'active',
    ]);
    app(BackfillHalaqaStructureAction::class)->execute();
    $engineEnrollment = CourseEnrollment::query()->findOrFail(
        OfferingHalaqaEnrollmentLink::query()->firstOrFail()->course_enrollment_id
    );

    return compact('admin', 'year', 'student', 'teacher', 'teacherUser', 'program', 'engineEnrollment');
}

it('lets a teacher recommend into the single milestone store and lists the board', function () {
    $ctx = seedMilestoneFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.milestones'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Teach/QuranMilestones')
            ->has('targets', 1)
            ->where('targets.0.student_name', 'Yusuf Ali')
            ->has('rows', 0)
            ->where('can_decide', false)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.milestones.store'), [
            'hifz_program_id' => $ctx['program']->id,
            'student_id' => $ctx['student']->id,
            'type' => 'juz_completed',
            'juz_number' => 30,
            'note' => 'Recited juz 30 end to end.',
        ])
        ->assertRedirect();

    // Rule 11: the write landed in hifz_milestones — the one milestone store.
    $milestone = HifzMilestone::query()->firstOrFail();
    expect($milestone->status->value)->toBe('pending')
        ->and((int) $milestone->recommended_by)->toBe($ctx['teacherUser']->id)
        ->and((int) $milestone->hifz_program_id)->toBe($ctx['program']->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.milestones'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.student_name', 'Yusuf Ali')
            ->where('rows.0.status', 'pending')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.milestones.store'), [
            'hifz_program_id' => $ctx['program']->id,
            'student_id' => $ctx['student']->id,
            'type' => 'bogus',
        ])
        ->assertSessionHasErrors('type');
});

it('review then approve rolls milestone completion onto the engine enrollment', function () {
    $ctx = seedMilestoneFixture();
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.milestones.store'), [
            'hifz_program_id' => $ctx['program']->id,
            'student_id' => $ctx['student']->id,
            'type' => 'quran_completed',
        ])
        ->assertRedirect();
    $milestone = HifzMilestone::query()->firstOrFail();

    // Teachers without courses.manage cannot review or decide.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.milestones.review', $milestone->id))
        ->assertForbidden();
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.milestones.decide', $milestone->id), ['approved' => true])
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->post(route('teach.milestones.review', $milestone->id), ['note' => 'Verified.'])
        ->assertRedirect();
    expect($milestone->refresh()->status->value)->toBe('supervisor_reviewed');

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->post(route('teach.milestones.decide', $milestone->id), ['approved' => true])
        ->assertRedirect();
    $milestone->refresh();
    expect($milestone->status->value)->toBe('approved')
        ->and((int) $milestone->approved_by)->toBe($ctx['admin']->id);

    // The decision synced straight through ADR-022's evaluator to the enrollment.
    $enrollment = $ctx['engineEnrollment']->refresh();
    expect((int) $enrollment->progress_percentage)->toBe(100)
        ->and($enrollment->status)->toBe('completed')
        ->and($enrollment->completed_at)->not->toBeNull();

    // A decided milestone cannot be decided again.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->post(route('teach.milestones.decide', $milestone->id), ['approved' => false])
        ->assertSessionHasErrors('status');
});

it('rejection leaves the enrollment incomplete and the board exports CSV', function () {
    $ctx = seedMilestoneFixture();
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.milestones.store'), [
            'hifz_program_id' => $ctx['program']->id,
            'student_id' => $ctx['student']->id,
            'type' => 'surah_completed',
            'surah_number' => 1,
        ])
        ->assertRedirect();
    $milestone = HifzMilestone::query()->firstOrFail();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->post(route('teach.milestones.decide', $milestone->id), ['approved' => false, 'note' => 'Not fluent yet.'])
        ->assertRedirect();
    expect($milestone->refresh()->status->value)->toBe('rejected');

    $enrollment = $ctx['engineEnrollment']->refresh();
    expect((int) $enrollment->progress_percentage)->toBe(0)
        ->and($enrollment->status)->not->toBe('completed');

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('teach.milestones', ['format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();
    expect($csv)->toContain('Halaqa An-Noor')->toContain('rejected');
});
