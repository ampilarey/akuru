<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\CopyPlanAction;
use App\Domains\Academics\Actions\GenerateExpectedRegistersAction;
use App\Domains\Academics\Actions\LockOverdueRegistersAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Actions\SubmitRegisterAction;
use App\Domains\Academics\Actions\UnlockRegisterAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Academics\Models\PlanTopic;
use App\Domains\Academics\Models\RegisterUnlock;
use App\Domains\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Freeze the clock inside the week these tests pin their lesson dates to.
 *
 * The register lock window (`register_lock_days`, default 7) is measured from
 * "now", but the fixtures below hardcode dates in 2026-08-24..31. On
 * 2026-08-31 the cutoff (`now()->startOfDay()->subDays(7)`) reached
 * 2026-08-24 and, because the comparison is inclusive, the register the
 * teacher fills became locked — so "it lets a teacher fill today" started
 * failing with no code change behind it. It passed on 2026-08-29 and failed on
 * 2026-08-31 for that reason alone.
 *
 * Freezing keeps the relationship the tests were written under. Fixtures that
 * already use relative dates (`now()->subDay()`) move with it and stay
 * distinct from the pinned ones.
 */
beforeEach(function () {
    test()->travelTo(Carbon::parse('2026-08-26 09:00:00', config('app.timezone')));
});

function mondaySlot(array $overrides = []): array
{
    $year = $overrides['year'] ?? makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    unset($overrides['year']);

    $class = $overrides['class'] ?? makeClass($year);
    unset($overrides['class']);

    $period = $overrides['period'] ?? makePeriodRow();
    unset($overrides['period']);

    return array_merge([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => makeTeacherRow()->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $period->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ], $overrides);
}

it('generates expected registers and skips days that affect the timetable', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $entry = app(SaveTimetableEntryAction::class)->execute(mondaySlot(['year' => $year]));

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-08-24',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'Holiday Monday',
        'affects_timetable' => true,
    ]);
    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-08-31',
        'type' => CalendarDayType::ExamDay->value,
        'title' => 'Exam Monday',
        'affects_timetable' => false,
    ]);

    $first = app(GenerateExpectedRegistersAction::class)->execute($year->id, '2026-08-24', '2026-08-31');

    expect($first['created'])->toBe(1)
        ->and(LessonLog::query()->whereDate('date', '2026-08-24')->count())->toBe(0)
        ->and(LessonLog::query()->whereDate('date', '2026-08-31')->count())->toBe(1);

    $log = LessonLog::query()->sole();
    expect($log->status)->toBe(LessonLogStatus::Expected)
        ->and($log->timetable_id)->toBe($entry->id)
        ->and($log->academic_year_id)->toBe($year->id)
        ->and($log->taught_summary)->toBeNull();

    $second = app(GenerateExpectedRegistersAction::class)->execute($year->id, '2026-08-24', '2026-08-31');
    expect($second['created'])->toBe(0)
        ->and($second['skipped'])->toBe(1)
        ->and(LessonLog::query()->count())->toBe(1);
});

it('submits a register, marks the plan topic taught, and rejects a locked log', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $class = makeClass($year);
    $subject = makeSubject();
    $plan = makeCoursePlan([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
    ]);
    $topic = PlanTopic::query()->create([
        'course_plan_id' => $plan->id,
        'order' => 1,
        'title' => 'Alif Baa',
        'is_completed' => false,
    ]);
    $log = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'period_id' => makePeriodRow()->id,
    ]);

    $submitted = app(SubmitRegisterAction::class)->execute(
        $log,
        ['plan_topic_id' => $topic->id, 'homework' => 'Page 3'],
        (int) $teacher->user_id,
    );

    expect($submitted->status)->toBe(LessonLogStatus::Submitted)
        ->and($submitted->taught_summary)->toBe('Alif Baa')
        ->and($submitted->homework)->toBe('Page 3')
        ->and($topic->fresh()->is_completed)->toBeTrue();

    $old = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'date' => now()->subDays(10)->toDateString(),
        'period_id' => makePeriodRow('09:00:00', '09:45:00', 2)->id,
    ]);

    expect(app(LockOverdueRegistersAction::class)->execute(7))->toBeGreaterThan(0)
        ->and($old->fresh()->status)->toBe(LessonLogStatus::Locked);

    expect(fn () => app(SubmitRegisterAction::class)->execute(
        $old->fresh(),
        ['taught_summary' => 'Too late'],
        (int) $teacher->user_id,
    ))->toThrow(ValidationException::class);

    $unlock = app(UnlockRegisterAction::class)->execute($old->fresh(), (int) $teacher->user_id, 'Admin correction');
    expect($unlock)->toBeInstanceOf(RegisterUnlock::class)
        ->and($old->fresh()->status)->toBe(LessonLogStatus::Draft)
        ->and(RegisterUnlock::query()->where('lesson_log_id', $old->id)->where('reason', 'Admin correction')->count())->toBe(1);

    $afterUnlock = app(SubmitRegisterAction::class)->execute(
        $old->fresh(),
        ['taught_summary' => 'Caught up'],
        (int) $teacher->user_id,
    );
    expect($afterUnlock->status)->toBe(LessonLogStatus::Submitted)
        ->and($afterUnlock->taught_summary)->toBe('Caught up');
});

it('copies a plan to another class and resets topic completion', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $sourceClass = makeClass($year, 'Grade 1', 'A');
    $targetClass = makeClass($year, 'Grade 1', 'B');
    $plan = makeCoursePlan(['year' => $year, 'classroom_id' => $sourceClass->id]);
    PlanTopic::query()->create([
        'course_plan_id' => $plan->id,
        'order' => 1,
        'title' => 'Done topic',
        'is_completed' => true,
    ]);

    $copy = app(CopyPlanAction::class)->execute($plan, [
        'classroom_id' => $targetClass->id,
        'academic_year_id' => $year->id,
    ]);

    expect($copy->id)->not->toBe($plan->id)
        ->and($copy->classroom_id)->toBe($targetClass->id)
        ->and($copy->topics)->toHaveCount(1)
        ->and($copy->topics->first()->is_completed)->toBeFalse()
        ->and($copy->topics->first()->title)->toBe('Done topic')
        ->and($plan->fresh()->topics->first()->is_completed)->toBeTrue();
});

it('lets a teacher fill today and exports the unfilled report', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $class = makeClass($year);
    $subject = makeSubject();
    $period = makePeriodRow();
    app(SaveTimetableEntryAction::class)->execute(mondaySlot([
        'year' => $year,
        'class' => $class,
        'period' => $period,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]));
    app(GenerateExpectedRegistersAction::class)->execute($year->id, '2026-08-24', '2026-08-24');

    $teacherUser = User::query()->findOrFail($teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $teacherUser->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('academics.registers.today', ['date' => '2026-08-24']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Today')
            ->has('registers', 1)
            ->where('registers.0.subject_name', $subject->name)
        );

    $log = LessonLog::query()->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->put(route('academics.registers.update', $log), [
            'taught_summary' => 'Noon recitation',
            'materials' => 'mushaf, whiteboard',
        ])
        ->assertRedirect();

    expect($log->fresh()->status)->toBe(LessonLogStatus::Submitted)
        ->and($log->fresh()->materials)->toBe(['mushaf', 'whiteboard']);

    $admin = actingPeopleAdmin(['registers.manage']);
    $past = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'date' => now()->subDay()->toDateString(),
        'period_id' => makePeriodRow('10:00:00', '10:45:00', 3)->id,
        'status' => 'expected',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.registers.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Unfilled')
            ->where('unfilled.0.id', $past->id)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.registers.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain($subject->name);

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('academics.registers.index'))
        ->assertForbidden();
});

it('forbids today without registers.fill', function () {
    $user = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertForbidden();
});

it('explains empty today and lets a teacher generate their own day', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $class = makeClass($year);
    $class->forceFill(['class_teacher_id' => $teacher->user_id])->save();
    $subject = makeSubject();
    $period = makePeriodRow();
    app(SaveTimetableEntryAction::class)->execute(mondaySlot([
        'year' => $year,
        'class' => $class,
        'period' => $period,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]));

    $teacherUser = User::query()->findOrFail($teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $teacherUser->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('academics.registers.today', ['date' => '2026-08-24']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Today')
            ->has('registers', 0)
            ->where('empty.code', 'not_generated')
            ->where('empty.can_generate', true)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->post(route('academics.registers.today.generate'), ['date' => '2026-08-24'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Created 1 expected registers.');

    expect(LessonLog::query()->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->post(route('academics.registers.today.generate'), ['date' => '2026-08-24'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Created 0 expected registers (1 already exist).');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('academics.registers.today', ['date' => '2026-08-24']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Today')
            ->has('registers', 1)
            ->where('empty', null)
        );
});

it('tells a login without a teachers row why today is empty', function () {
    $user = actingPeopleAdmin(['registers.fill']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today', ['date' => '2026-08-24']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Today')
            ->where('empty.code', 'no_teacher')
            ->where('empty.can_generate', false)
        );
});

it('shows student number and date of birth on the register fill grid', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $class = makeClass($year);
    $student = makeStudent([
        'first_name' => 'Mariyam',
        'last_name' => 'Ali',
        'student_id' => 'PIL-03',
        'date_of_birth' => '2011-05-05',
    ]);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);
    $log = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'period_id' => makePeriodRow()->id,
    ]);

    $teacherUser = User::query()->findOrFail($teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $teacherUser->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get(route('academics.registers.show', $log))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Show')
            ->has('roster', 1)
            ->where('roster.0.student_number', 'PIL-03')
            ->where('roster.0.date_of_birth', '2011-05-05')
        );
});
