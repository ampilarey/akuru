<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveExamMarkAction;
use App\Domains\ExamsGrades\Actions\SaveWeightSchemeAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Contracts\GradeItemContract;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\ImportAssessmentAttemptAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function unifiedAdmin(): User
{
    return actingPeopleAdmin(['exams.manage', 'exams.enter-any', 'courses.manage']);
}

function unifiedSetup(): array
{
    $admin = unifiedAdmin();
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 5', 'A');
    $subject = makeSubject();
    $otherSubject = makeSubject();
    $final = ExamType::query()->where('code', ExamTypeCode::Final)->sole();

    $weights = [];
    foreach (ExamType::query()->get() as $type) {
        $weights[$type->id] = $type->code === ExamTypeCode::Final ? 100 : 0;
    }
    app(SaveWeightSchemeAction::class)->execute([
        'academic_year_id' => $year->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'weights' => $weights,
    ]);

    $aisha = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Ali']);
    $bilal = makeStudent(['first_name' => 'Bilal', 'last_name' => 'Hassan']);
    app(AssignStudentToClassAction::class)->execute($class, $aisha->id, '2026-01-01');
    app(AssignStudentToClassAction::class)->execute($class, $bilal->id, '2026-01-01');

    return compact('admin', 'year', 'term', 'class', 'subject', 'otherSubject', 'final', 'aisha', 'bilal');
}

it('puts exam marks and class assessment scores in one gradebook without subject branches', function () {
    $src = file_get_contents(app_path('Domains/ExamsGrades/Actions/ListGradebookAction.php'));
    expect($src)->not->toContain('course_type')
        ->and(strtolower($src))->not->toContain('hifz')
        ->and(strtolower($src))->not->toContain('arabic')
        ->and(strtolower($src))->not->toContain('quran');

    expect(interface_exists(GradeItemContract::class))->toBeTrue();

    $ctx = unifiedSetup();
    $exam = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['final']->id,
        'name' => 'Final',
        'exam_date' => '2026-08-24',
        'max_marks' => 100,
    ], null, $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($exam, ExamStatus::MarksEntry, $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($exam, $ctx['aisha']->id, ['marks' => 90], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($exam, $ctx['bilal']->id, ['is_absent' => true], $ctx['admin']->id);

    $quiz = app(SaveAssessmentAction::class)->execute([
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'title' => 'Grade 5 Letters Quiz',
        'status' => 'published',
        'max_score' => 10,
        'created_by' => $ctx['admin']->id,
        'settings' => ['school_subject_id' => $ctx['subject']->id],
    ]);
    $homework = app(SaveAssessmentAction::class)->execute([
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'title' => 'Recitation homework',
        'status' => 'published',
        'assessment_type' => 'assignment',
        'max_score' => 10,
        'created_by' => $ctx['admin']->id,
        'settings' => ['school_subject_id' => $ctx['subject']->id],
    ]);
    $draft = app(SaveAssessmentAction::class)->execute([
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'title' => 'Hidden draft',
        'status' => 'draft',
        'max_score' => 5,
        'created_by' => $ctx['admin']->id,
        'settings' => ['school_subject_id' => $ctx['subject']->id],
    ]);
    $otherQuiz = app(SaveAssessmentAction::class)->execute([
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'title' => 'Math drill',
        'status' => 'published',
        'max_score' => 20,
        'created_by' => $ctx['admin']->id,
        'settings' => ['school_subject_id' => $ctx['otherSubject']->id],
    ]);
    $classWide = app(SaveAssessmentAction::class)->execute([
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'title' => 'Homeroom check',
        'status' => 'published',
        'max_score' => 5,
        'created_by' => $ctx['admin']->id,
    ]);

    app(ImportAssessmentAttemptAction::class)->execute([
        'assessment_id' => $quiz->id,
        'student_id' => $ctx['aisha']->id,
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'attempt_number' => 1,
        'status' => 'scored',
        'score' => 8,
        'max_score' => 10,
        'submitted_at' => now(),
    ]);
    app(ImportAssessmentAttemptAction::class)->execute([
        'assessment_id' => $quiz->id,
        'student_id' => $ctx['aisha']->id,
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'attempt_number' => 2,
        'status' => 'in_progress',
        'score' => null,
        'max_score' => 10,
    ]);
    app(ImportAssessmentAttemptAction::class)->execute([
        'assessment_id' => $homework->id,
        'student_id' => $ctx['aisha']->id,
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'attempt_number' => 1,
        'status' => 'submitted',
        'score' => null,
        'max_score' => 10,
        'submitted_at' => now(),
    ]);
    app(ImportAssessmentAttemptAction::class)->execute([
        'assessment_id' => $classWide->id,
        'student_id' => $ctx['bilal']->id,
        'classroom_id' => $ctx['class']->id,
        'academic_year_id' => $ctx['year']->id,
        'attempt_number' => 1,
        'status' => 'scored',
        'score' => 4,
        'max_score' => 5,
        'submitted_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.gradebook.index', [
            'class_id' => $ctx['class']->id,
            'subject_id' => $ctx['subject']->id,
            'term_id' => $ctx['term']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Gradebook/Index')
            ->has('exams', 1)
            ->has('grade_items')
            ->where('grade_items', fn ($items) => collect($items)->pluck('key')->all() === [
                'exam:'.$exam->id,
                'assessment:'.$quiz->id,
                'assessment:'.$homework->id,
                'assessment:'.$classWide->id,
            ])
            ->where('rows.0.name', 'Aisha Ali')
            ->where('rows.0.marks.'.$exam->id.'.marks', fn ($marks) => (float) $marks === 90.0)
            ->where('rows.0.items.exam:'.$exam->id.'.score', fn ($score) => (float) $score === 90.0)
            ->where('rows.0.items.assessment:'.$quiz->id.'.score', fn ($score) => (float) $score === 8.0)
            ->where('rows.0.items.assessment:'.$homework->id.'.status', 'submitted')
            ->where('rows.1.items.exam:'.$exam->id.'.is_absent', true)
            ->where('rows.1.items.assessment:'.$classWide->id.'.score', fn ($score) => (float) $score === 4.0)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.gradebook.index', [
            'class_id' => $ctx['class']->id,
            'subject_id' => $ctx['otherSubject']->id,
            'term_id' => $ctx['term']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('grade_items', fn ($items) => collect($items)->pluck('key')->all() === [
                'assessment:'.$otherQuiz->id,
                'assessment:'.$classWide->id,
            ])
            ->where('grade_items', fn ($items) => ! collect($items)->contains('key', 'assessment:'.$quiz->id)
                && ! collect($items)->contains('key', 'assessment:'.$draft->id))
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.gradebook.export', [
            'class_id' => $ctx['class']->id,
            'subject_id' => $ctx['subject']->id,
            'term_id' => $ctx['term']->id,
        ]));

    $csv->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $csv->streamedContent();
    expect($body)->toContain('Grade 5 Letters Quiz')
        ->and($body)->toContain('Recitation homework')
        ->and($body)->toContain('Homeroom check')
        ->and($body)->toContain('Pending')
        ->and($body)->toContain('Abs')
        ->and($body)->toContain('8');
});
