<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\ExamsGrades\Actions\ComputeTermGradesAction;
use App\Domains\ExamsGrades\Actions\SaveCompetencyAction;
use App\Domains\ExamsGrades\Actions\SaveCompetencyAssessmentAction;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveExamMarkAction;
use App\Domains\ExamsGrades\Actions\SaveWeightSchemeAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\ExamsGrades\Models\TermGrade;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function termAdmin(): User
{
    return actingPeopleAdmin(['exams.manage', 'exams.enter-any']);
}

function publishExam($exam, User $admin): void
{
    foreach ([ExamStatus::MarksEntry, ExamStatus::Review, ExamStatus::Published] as $status) {
        app(TransitionExamStatusAction::class)->execute($exam->fresh(), $status, $admin->id);
    }
}

function termSetup(): array
{
    $admin = termAdmin();
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $subject = makeSubject();
    $quiz = ExamType::query()->where('code', ExamTypeCode::Quiz)->sole();
    $final = ExamType::query()->where('code', ExamTypeCode::Final)->sole();
    $midterm = ExamType::query()->where('code', ExamTypeCode::Midterm)->sole();

    $weights = [];
    foreach (ExamType::query()->get() as $type) {
        $weights[$type->id] = match ($type->code) {
            ExamTypeCode::Quiz => 20,
            ExamTypeCode::Final => 80,
            default => 0,
        };
    }
    app(SaveWeightSchemeAction::class)->execute([
        'academic_year_id' => $year->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'weights' => $weights,
    ]);

    $a = makeStudent(['first_name' => 'Aisha']);
    $b = makeStudent(['first_name' => 'Bilal']);
    app(AssignStudentToClassAction::class)->execute($class, $a->id, '2026-01-01');
    app(AssignStudentToClassAction::class)->execute($class, $b->id, '2026-01-01');

    return compact('admin', 'year', 'term', 'class', 'subject', 'quiz', 'final', 'midterm', 'a', 'b');
}

it('shares type weight equally and writes idempotent components', function () {
    $ctx = termSetup();
    $quizOne = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['quiz']->id,
        'name' => 'Quiz 1',
        'exam_date' => '2026-08-10',
        'max_marks' => 10,
    ], null, $ctx['admin']->id);
    $quizTwo = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['quiz']->id,
        'name' => 'Quiz 2',
        'exam_date' => '2026-08-17',
        'max_marks' => 10,
        'confirm_same_day' => true,
    ], null, $ctx['admin']->id);
    $final = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['final']->id,
        'name' => 'Final',
        'exam_date' => '2026-08-24',
        'max_marks' => 100,
        'confirm_same_day' => true,
    ], null, $ctx['admin']->id);

    app(TransitionExamStatusAction::class)->execute($quizOne, ExamStatus::MarksEntry, $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($quizTwo, ExamStatus::MarksEntry, $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($final, ExamStatus::MarksEntry, $ctx['admin']->id);

    app(SaveExamMarkAction::class)->execute($quizOne, $ctx['a']->id, ['marks' => 10], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($quizTwo, $ctx['a']->id, ['marks' => 8], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($final, $ctx['a']->id, ['marks' => 80], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($quizOne, $ctx['b']->id, ['marks' => 10], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($quizTwo, $ctx['b']->id, ['marks' => 8], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($final, $ctx['b']->id, ['marks' => 80], $ctx['admin']->id);

    publishExam($quizOne, $ctx['admin']);
    publishExam($quizTwo, $ctx['admin']);
    publishExam($final, $ctx['admin']);

    $first = app(ComputeTermGradesAction::class)->execute($ctx['class']->id, $ctx['subject']->id, $ctx['term']->id);
    $second = app(ComputeTermGradesAction::class)->execute($ctx['class']->id, $ctx['subject']->id, $ctx['term']->id);

    expect(TermGrade::query()->count())->toBe(2)
        ->and($first->count())->toBe($second->count());

    $aisha = TermGrade::query()->where('student_id', $ctx['a']->id)->sole();
    // Quizzes 20% shared 10+10; 100% and 80% → 10 + 8 = 18 of 20. Final 80% of 80% = 64. Total 82.
    expect((float) $aisha->weighted_percent)->toBe(82.0)
        ->and($aisha->grade)->toBe('B')
        ->and($aisha->components)->toHaveCount(3)
        ->and($aisha->components[0]['share'])->toBe(10)
        ->and($aisha->components[1]['share'])->toBe(10)
        ->and($aisha->components[2]['share'])->toBe(80)
        ->and($aisha->rank)->toBe(1)
        ->and(TermGrade::query()->where('student_id', $ctx['b']->id)->sole()->rank)->toBe(1);

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
            ->has('rows', 2)
        );
});

it('treats absent as zero unless the exclude setting is on, and skips exempt', function () {
    $ctx = termSetup();
    $final = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['final']->id,
        'name' => 'Final',
        'exam_date' => '2026-08-24',
        'max_marks' => 100,
    ], null, $ctx['admin']->id);
    $quiz = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['quiz']->id,
        'name' => 'Quiz',
        'exam_date' => '2026-08-10',
        'max_marks' => 10,
        'confirm_same_day' => true,
    ], null, $ctx['admin']->id);

    app(TransitionExamStatusAction::class)->execute($final, ExamStatus::MarksEntry, $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($quiz, ExamStatus::MarksEntry, $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($final, $ctx['a']->id, ['is_absent' => true], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($quiz, $ctx['a']->id, ['marks' => 10], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($final, $ctx['b']->id, ['is_exempt' => true], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($quiz, $ctx['b']->id, ['marks' => 10], $ctx['admin']->id);
    publishExam($final, $ctx['admin']);
    publishExam($quiz, $ctx['admin']);

    $grades = app(ComputeTermGradesAction::class)->execute($ctx['class']->id, $ctx['subject']->id, $ctx['term']->id);
    $aisha = $grades->firstWhere('student_id', $ctx['a']->id);
    $bilal = $grades->firstWhere('student_id', $ctx['b']->id);

    expect((float) $aisha->weighted_percent)->toBe(20.0)
        ->and((float) $bilal->weighted_percent)->toBe(20.0);

    DB::table('settings')->where('key', 'exams_exclude_absent')->update(['value' => '1']);
    $again = app(ComputeTermGradesAction::class)->execute($ctx['class']->id, $ctx['subject']->id, $ctx['term']->id);
    expect((float) $again->firstWhere('student_id', $ctx['a']->id)->weighted_percent)->toBe(20.0);
});

it('recomputes rank after a mark correction', function () {
    $ctx = termSetup();
    $final = app(SaveExamAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['final']->id,
        'name' => 'Final',
        'exam_date' => '2026-08-24',
        'max_marks' => 100,
    ], null, $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($final, ExamStatus::MarksEntry, $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($final, $ctx['a']->id, ['marks' => 90], $ctx['admin']->id);
    app(SaveExamMarkAction::class)->execute($final, $ctx['b']->id, ['marks' => 70], $ctx['admin']->id);
    publishExam($final, $ctx['admin']);
    app(ComputeTermGradesAction::class)->execute($ctx['class']->id, $ctx['subject']->id, $ctx['term']->id);

    expect(TermGrade::query()->where('student_id', $ctx['a']->id)->sole()->rank)->toBe(1)
        ->and(TermGrade::query()->where('student_id', $ctx['b']->id)->sole()->rank)->toBe(2);

    app(TransitionExamStatusAction::class)->execute($final->fresh(), ExamStatus::Locked, $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($final->fresh(), ExamStatus::Review, $ctx['admin']->id, 'correct marks');
    app(SaveExamMarkAction::class)->execute($final->fresh(), $ctx['b']->id, ['marks' => 95], $ctx['admin']->id);
    app(TransitionExamStatusAction::class)->execute($final->fresh(), ExamStatus::Published, $ctx['admin']->id);
    app(ComputeTermGradesAction::class)->execute($ctx['class']->id, $ctx['subject']->id, $ctx['term']->id);

    expect(TermGrade::query()->where('student_id', $ctx['b']->id)->sole()->rank)->toBe(1)
        ->and(TermGrade::query()->where('student_id', $ctx['a']->id)->sole()->rank)->toBe(2);
});

it('stores competency assessments and lists them on the gradebook', function () {
    $ctx = termSetup();
    $competency = app(SaveCompetencyAction::class)->execute([
        'subject_id' => $ctx['subject']->id,
        'name' => 'Recitation fluency',
    ]);
    app(SaveCompetencyAssessmentAction::class)->execute([
        'student_id' => $ctx['a']->id,
        'competency_id' => $competency->id,
        'term_id' => $ctx['term']->id,
        'level' => 'mastered',
    ], $ctx['admin']->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.gradebook.index', [
            'class_id' => $ctx['class']->id,
            'subject_id' => $ctx['subject']->id,
            'term_id' => $ctx['term']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competencies', 1)
            ->where('rows.0.competencies.'.$competency->id, 'mastered')
        );
});
