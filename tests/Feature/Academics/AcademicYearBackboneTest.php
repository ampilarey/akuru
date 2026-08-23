<?php

use App\Domains\Academics\Actions\ActivateAcademicYearAction;
use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\CloseAcademicYearAction;
use App\Domains\Academics\Actions\PromoteStudentsAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Enums\TermStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Student;
use App\Domains\Settings\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function makeYear(array $overrides = []): AcademicYear
{
    return AcademicYear::query()->create(array_merge([
        'name' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => false,
        'status' => AcademicYearStatus::Upcoming,
    ], $overrides));
}

function makeSchool(): School
{
    return School::query()->first() ?? School::query()->create([
        'name' => 'Test School',
        'address' => 'Malé',
        'phone' => '7972434',
        'email' => 'school@example.com',
        'principal_name' => 'Principal',
        'established_year' => '2010',
        'is_active' => true,
    ]);
}

function makeClass(AcademicYear $year, string $name = 'Grade 1', string $section = 'A'): ClassRoom
{
    return ClassRoom::query()->create([
        'school_id' => makeSchool()->id,
        'academic_year_id' => $year->id,
        'name' => $name,
        'section' => $section,
        'level' => 'Primary',
        'capacity' => 20,
        'is_active' => true,
    ]);
}

it('enforces a single active academic year and close-after-terms', function () {
    $first = makeYear(['name' => '2025-2026']);
    app(ActivateAcademicYearAction::class)->execute($first);

    $second = makeYear(['name' => '2026-2027']);
    expect(fn () => app(ActivateAcademicYearAction::class)->execute($second))
        ->toThrow(RuntimeException::class);

    $term = $first->termRecords()->create([
        'name' => 'Term 1',
        'start_date' => '2025-01-01',
        'end_date' => '2025-06-30',
        'status' => TermStatus::Active,
        'sort_order' => 1,
    ]);

    expect(fn () => app(CloseAcademicYearAction::class)->execute($first->refresh()))
        ->toThrow(RuntimeException::class);

    $term->forceFill(['status' => TermStatus::Closed])->save();
    app(CloseAcademicYearAction::class)->execute($first->refresh());

    expect($first->refresh()->status)->toBe(AcademicYearStatus::Closed)
        ->and($first->is_current)->toBeFalse();

    app(ActivateAcademicYearAction::class)->execute($second);
    expect($second->refresh()->status)->toBe(AcademicYearStatus::Active);
});

it('keeps legacy term columns and adds unified_term_id plus terms table', function () {
    expect(Schema::hasColumn('academic_years', 'terms'))->toBeTrue()
        ->and(Schema::hasColumn('course_enrollments', 'term_id'))->toBeTrue()
        ->and(Schema::hasColumn('course_enrollments', 'term_key'))->toBeTrue()
        ->and(Schema::hasColumn('course_enrollments', 'unified_term_id'))->toBeTrue()
        ->and(Schema::hasTable('terms'))->toBeTrue()
        ->and(Schema::hasTable('class_student'))->toBeTrue();
});

it('dry-runs promotion without writes and then promote/repeat/leave/graduate', function () {
    $actor = actingPeopleAdmin();
    $sourceYear = makeYear(['name' => '2025-2026', 'status' => AcademicYearStatus::Active, 'is_current' => true]);
    $targetYear = makeYear(['name' => '2026-2027']);
    $sourceClass = makeClass($sourceYear, 'Grade 5', 'A');
    $targetClass = makeClass($targetYear, 'Grade 6', 'A');

    $promote = makeStudent(['first_name' => 'Promote']);
    $repeat = makeStudent(['first_name' => 'Repeat']);
    $leave = makeStudent(['first_name' => 'Leave']);
    $graduate = makeStudent(['first_name' => 'Graduate']);

    $assign = app(AssignStudentToClassAction::class);
    foreach ([$promote, $repeat, $leave, $graduate] as $student) {
        $assign->execute($sourceClass, $student->id);
    }

    $action = app(PromoteStudentsAction::class);
    $overrides = [
        $promote->id => 'promote',
        $repeat->id => 'repeat',
        $leave->id => 'leave',
        $graduate->id => 'graduate',
    ];
    $classMap = [$sourceClass->id => $targetClass->id];

    $dry = $action->execute($sourceYear->id, $targetYear->id, $classMap, $overrides, true, $actor->id);

    expect($dry['dry_run'])->toBeTrue()
        ->and(ClassStudent::query()->where('status', ClassStudentStatus::Active->value)->count())->toBe(4)
        ->and(Student::query()->whereIn('id', [$leave->id, $graduate->id])->pluck('status')->every(
            fn ($status) => $status === StudentStatus::Active
        ))->toBeTrue();

    $action->rememberDryRun($sourceYear->id, $targetYear->id);
    $action->execute($sourceYear->id, $targetYear->id, $classMap, $overrides, false, $actor->id);

    expect($promote->fresh()->class_id)->toBe($targetClass->id)
        ->and($repeat->fresh()->class_id)->toBe($sourceClass->id)
        ->and($repeat->fresh()->status)->toBe(StudentStatus::Active)
        ->and($leave->fresh()->status)->toBe(StudentStatus::Withdrawn)
        ->and($leave->fresh()->class_id)->toBeNull()
        ->and($graduate->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and(ClassStudent::query()->where('student_id', $promote->id)->where('class_id', $targetClass->id)->where('status', 'active')->exists())->toBeTrue()
        ->and(ClassStudent::query()->where('student_id', $repeat->id)->where('status', 'active')->exists())->toBeTrue();
});

it('loads the academic year and promotion screens', function () {
    $admin = actingPeopleAdmin();
    makeYear();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.years.index'))
        ->assertOk();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.promotion.create'))
        ->assertOk();
});
