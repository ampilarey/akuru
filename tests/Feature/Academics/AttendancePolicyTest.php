<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ResolveAttendanceSettingsAction;
use App\Domains\Academics\Actions\SaveAttendanceSettingsAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E10d — the rounding policy for part-lessons, and an editor for a policy that
 * has been readable since August and writable by nobody.
 *
 * The load-bearing property is the last test: **the register is never
 * rewritten.** Turning the rule off must bring the old figures back.
 */
function policySetup(): array
{
    $year = makeYear(['name' => 'Policy year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);
    $class = makeClass($year);

    Role::findOrCreate('admin', 'web');
    $staff = User::factory()->create(['name' => 'Office']);
    $staff->assignRole('admin');

    $student = makeStudent(['first_name' => 'Nashwa', 'last_name' => 'Ali']);
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    return ['staff' => $staff->fresh(), 'student' => $student, 'year' => $year, 'class' => $class];
}

/** Four lessons: two on time, one 5 minutes late, one 30 minutes late. */
function seedRegister(array $seed): void
{
    $rows = [
        [AttendanceStatus::Present->value, null],
        [AttendanceStatus::Present->value, null],
        [AttendanceStatus::Late->value, 5],
        [AttendanceStatus::Late->value, 30],
    ];

    foreach ($rows as $i => [$status, $late]) {
        ClassAttendance::query()->create([
            'student_id' => $seed['student']->id,
            'class_id' => $seed['class']->id,
            'academic_year_id' => $seed['year']->id,
            'date' => now()->subDays($i)->toDateString(),
            'period_key' => $i,
            'status' => $status,
            'minutes_late' => $late,
            'marked_by' => $seed['staff']->id,
        ]);
    }
}

it('counts every late mark as attended while the rule is off', function () {
    // The behaviour before this slice, and still the default: a pupil 30
    // minutes into a lesson scores the same as one who was on time.
    $seed = policySetup();
    ['staff' => $staff, 'student' => $student] = $seed;
    seedRegister($seed);

    expect(app(ResolveAttendanceSettingsAction::class)->execute()['part_lesson_minutes'])->toBe(0);

    $summary = app(ListClassAttendanceAction::class)->studentSummary((int) $student->id)->first();

    expect($summary['percent'])->toBe(100.0)
        ->and($summary['part_lessons'])->toBe(0)
        ->and($summary['percent_before_rounding'])->toBe(100.0);
});

it('stops counting a lesson the pupil mostly missed', function () {
    $seed = policySetup();
    ['staff' => $staff, 'student' => $student] = $seed;
    seedRegister($seed);

    app(SaveAttendanceSettingsAction::class)->execute([
        'mode' => 'per_lesson',
        'notify' => 'absent_only',
        'chronic_threshold' => 5,
        'tardies_per_absence' => 0,
        'part_lesson_minutes' => 20,
    ]);

    $summary = app(ListClassAttendanceAction::class)->studentSummary((int) $student->id)->first();

    // The 30-minute arrival stops counting; the 5-minute one still does.
    expect($summary['part_lessons'])->toBe(1)
        ->and($summary['percent'])->toBe(75.0)
        // The old number travels with it, so a figure that moved can be
        // explained rather than argued about.
        ->and($summary['percent_before_rounding'])->toBe(100.0)
        ->and($summary['part_lesson_minutes'])->toBe(20);

    unset($staff);
});

it('never rewrites the register, so turning the rule off restores the figures', function () {
    // The whole reason the rounding is applied at read time. A school that
    // changes its mind must get its old numbers back, not a rewritten history.
    $seed = policySetup();
    ['staff' => $staff, 'student' => $student] = $seed;
    seedRegister($seed);

    $before = ClassAttendance::query()->where('student_id', $student->id)
        ->orderBy('date')->get(['status', 'minutes_late'])->toArray();

    app(SaveAttendanceSettingsAction::class)->execute([
        'mode' => 'per_lesson', 'notify' => 'absent_only', 'chronic_threshold' => 5,
        // 5 rather than 10, so both late marks cross it — and the 5-minute
        // one sits exactly on the boundary, which is inclusive.
        'tardies_per_absence' => 0, 'part_lesson_minutes' => 5,
    ]);

    expect(app(ListClassAttendanceAction::class)->studentSummary((int) $student->id)->first()['percent'])
        ->toBe(50.0);

    // The marks themselves are untouched: still `late`, still with the minutes.
    expect(ClassAttendance::query()->where('student_id', $student->id)
        ->orderBy('date')->get(['status', 'minutes_late'])->toArray())->toBe($before);

    // Off again, and the original figure returns.
    app(SaveAttendanceSettingsAction::class)->execute([
        'mode' => 'per_lesson', 'notify' => 'absent_only', 'chronic_threshold' => 5,
        'tardies_per_absence' => 0, 'part_lesson_minutes' => 0,
    ]);

    $after = app(ListClassAttendanceAction::class)->studentSummary((int) $student->id)->first();
    expect($after['percent'])->toBe(100.0)->and($after['part_lessons'])->toBe(0);
});

it('refuses a policy that would quietly break the figures', function () {
    ['staff' => $staff] = policySetup();
    $save = app(SaveAttendanceSettingsAction::class);

    $valid = [
        'mode' => 'per_lesson', 'notify' => 'absent_only',
        'chronic_threshold' => 5, 'tardies_per_absence' => 0, 'part_lesson_minutes' => 0,
    ];

    foreach ([
        ['mode' => 'whenever'],
        ['notify' => 'shout'],
        ['chronic_threshold' => 0],
        ['tardies_per_absence' => -1],
        ['part_lesson_minutes' => 1000],
    ] as $bad) {
        expect(fn () => $save->execute([...$valid, ...$bad]))->toThrow(ValidationException::class);
    }

    unset($staff);
});

it('gives the school a screen for a policy only a DBA could change before', function () {
    // ResolveAttendanceSettingsAction has read these keys since August and
    // nothing has ever written them — SettingsController has only index and
    // clearCache.
    ['staff' => $staff] = policySetup();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.attendance-policy.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Academics/AttendancePolicy/Index')
            ->where('settings.part_lesson_minutes', 0)
            ->etc());

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->put(route('academics.attendance-policy.update'), [
            'mode' => 'daily',
            'notify' => 'absent_and_late',
            'chronic_threshold' => 8,
            'tardies_per_absence' => 3,
            'part_lesson_minutes' => 15,
        ])->assertSessionHasNoErrors();

    $settings = app(ResolveAttendanceSettingsAction::class)->execute();

    expect($settings['mode']->value)->toBe('daily')
        ->and($settings['notify'])->toBe('absent_and_late')
        ->and($settings['chronic_threshold'])->toBe(8)
        ->and($settings['tardies_per_absence'])->toBe(3)
        ->and($settings['part_lesson_minutes'])->toBe(15);

    // And a family cannot set the school's policy.
    Role::findOrCreate('parent', 'web');
    $parent = User::factory()->create();
    $parent->assignRole('parent');

    $this->withoutLocalizationMiddleware()->actingAs($parent->fresh())
        ->get(route('academics.attendance-policy.index'))->assertForbidden();
});
