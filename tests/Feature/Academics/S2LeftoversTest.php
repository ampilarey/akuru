<?php

use App\Domains\Academics\Actions\SaveCoursePlanAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\CoursePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Two S2 leftovers the 2026-09-22 audit found (STATUS §5eu): the pre-S2
 * `attendance` table that nothing read or wrote, and the plan-year string
 * `SaveCoursePlanAction` was still writing beside `academic_year_id`.
 */
it('has dropped the pre-S2 attendance table and its morph alias', function () {
    expect(Schema::hasTable('attendance'))->toBeFalse()
        ->and(Schema::hasTable('class_attendance'))->toBeTrue()
        ->and(array_key_exists('attendance', config('morph-map')))->toBeFalse()
        ->and(class_exists(\App\Domains\Academics\Models\Attendance::class))->toBeFalse();
});

it('saves a plan by year FK alone and still shows the year name', function () {
    $year = makeYear(['name' => '2027-2028', 'status' => AcademicYearStatus::Active]);
    $class = makeClass($year, 'Plan class');
    $subject = makeSubject();
    $teacher = makeTeacherRow();

    $plan = app(SaveCoursePlanAction::class)->execute([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'academic_year_id' => $year->id,
        'title' => 'Term plan',
    ]);

    // The string column is no longer written…
    $column = collect(Schema::getColumns('course_plans'))->firstWhere('name', 'academic_year');

    expect(CoursePlan::query()->whereKey($plan->id)->value('academic_year'))->toBeNull()
        ->and($column['nullable'] ?? false)->toBeTrue();

    // …and the screen still says which year, from the FK.
    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin(['registers.manage']))
        ->get('/academics/plans')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans.0.academic_year', '2027-2028'));
});
