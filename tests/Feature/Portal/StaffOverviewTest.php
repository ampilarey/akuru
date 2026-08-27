<?php

use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\PlanTopic;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('composes staff overview from unfilled registers, ungraded exams, and plan adherence', function () {
    $admin = actingPeopleAdmin(['registers.manage', 'exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 5', 'B');
    $subject = makeSubject();
    $teacher = makeTeacherRow();
    $past = now()->subDay()->toDateString();

    makeLessonLog([
        'year' => $year,
        'academic_year_id' => $year->id,
        'classroom_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'date' => $past,
        'status' => LessonLogStatus::Expected->value,
    ]);
    makeLessonLog([
        'year' => $year,
        'academic_year_id' => $year->id,
        'classroom_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'date' => $past,
        'status' => LessonLogStatus::Submitted->value,
        'taught_summary' => 'Covered alif',
        'submitted_at' => now()->subDay(),
    ]);

    $plan = makeCoursePlan([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'title' => 'Arabic Term 1',
    ]);
    PlanTopic::query()->create([
        'course_plan_id' => $plan->id,
        'order' => 1,
        'title' => 'Alif',
        'is_completed' => true,
    ]);
    PlanTopic::query()->create([
        'course_plan_id' => $plan->id,
        'order' => 2,
        'title' => 'Baa',
        'is_completed' => false,
    ]);

    $type = ExamType::query()->where('code', ExamTypeCode::Final)->sole();
    $exam = app(SaveExamAction::class)->execute([
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'exam_type_id' => $type->id,
        'name' => 'Ungraded Final',
        'exam_date' => $past,
        'max_marks' => 100,
    ], null, $admin->id);
    app(TransitionExamStatusAction::class)->execute($exam, ExamStatus::MarksEntry, $admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.overview'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('portal.overview', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/StaffOverview')
            ->where('title', 'Staff overview')
            ->where('yearId', $year->id)
            ->has('unfilled', 1)
            ->where('unfilled.0.class_name', 'Grade 5 B')
            ->where('unfilled.0.status', LessonLogStatus::Expected->value)
            ->has('ungraded', 1)
            ->where('ungraded.0.name', 'Ungraded Final')
            ->where('ungraded.0.status', ExamStatus::MarksEntry->value)
            ->has('fillRates', 1)
            ->where('fillRates.0.filled', 1)
            ->where('fillRates.0.total', 2)
            ->where('fillRates.0.rate', 50)
            ->has('planAdherence', 1)
            ->where('planAdherence.0.title', 'Arabic Term 1')
            ->where('planAdherence.0.completed', 1)
            ->where('planAdherence.0.total', 2)
            ->where('planAdherence.0.rate', 50)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('portal.overview.export', ['academic_year_id' => $year->id]));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Grade 5 B')
        ->and($csv->streamedContent())->toContain('Ungraded Final')
        ->and($csv->streamedContent())->toContain('Arabic Term 1')
        ->and($csv->streamedContent())->toContain('Fatimat Ali');
});

it('forbids parents from the staff overview', function () {
    Role::findOrCreate('parent', 'web');
    $user = User::factory()->create();
    $user->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.overview'))
        ->assertForbidden();
});

it('does not import other domain models or the Hifz namespace from new staff overview files', function () {
    $files = [
        app_path('Domains/Portal/Actions/ComposeStaffOverviewAction.php'),
        app_path('Domains/Portal/Http/Controllers/StaffOverviewController.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toMatch('/App\\\\Domains\\\\[A-Za-z]+\\\\Models\\\\/');
    }
});
