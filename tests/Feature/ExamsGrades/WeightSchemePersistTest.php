<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\ExamsGrades\Actions\ComputeTermGradesAction;
use App\Domains\ExamsGrades\Actions\GenerateReportCardsAction;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveExamMarkAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\AssessmentWeightScheme;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('saves a usable scheme over HTTP and fills term percent grade rank on a report card', function () {
    $admin = actingPeopleAdmin(['exams.manage', 'exams.enter-any']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 5', 'A');
    $subject = makeSubject();
    $student = makeStudent(['first_name' => 'Fatima', 'last_name' => 'Yoosuf', 'student_id' => 'PIL-01']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');

    $weights = ExamType::query()
        ->get()
        ->mapWithKeys(fn (ExamType $type) => [(string) $type->id => (int) $type->default_weight])
        ->all();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.weights.store'), [
            'academic_year_id' => $year->id,
            'weights' => $weights,
        ])
        ->assertRedirect();

    expect(AssessmentWeightScheme::query()->count())->toBe(1);

    $final = ExamType::query()->where('code', ExamTypeCode::Final)->sole();
    $exam = app(SaveExamAction::class)->execute([
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'exam_type_id' => $final->id,
        'name' => 'Final',
        'exam_date' => '2026-08-24',
        'max_marks' => 100,
    ], null, $admin->id);

    app(TransitionExamStatusAction::class)->execute($exam, ExamStatus::MarksEntry, $admin->id);
    app(SaveExamMarkAction::class)->execute($exam, $student->id, ['marks' => 90], $admin->id);
    foreach ([ExamStatus::Review, ExamStatus::Published] as $status) {
        app(TransitionExamStatusAction::class)->execute($exam->fresh(), $status, $admin->id);
    }

    $grades = app(ComputeTermGradesAction::class)->execute($class->id, $subject->id, $term->id);
    $row = $grades->firstWhere('student_id', $student->id);

    // 90/100 on the only published exam of the term is 90% for the term.
    //
    // This used to assert 36.0 and grade 'E' — a fail — because the Final's
    // default weight is 40 and the scheme's other 60 belongs to exam types
    // that have no published exam yet. That share was scored as nothing rather
    // than taken out of the divisor, so every pupil in every partly-examined
    // term was deflated by however much of the year had not happened.
    // `ComputeTermGradesAction::student()` now renormalises to the share that
    // actually counted (S3_SPEC §S3.3).
    expect($row)->not->toBeNull()
        ->and($row->weighted_percent)->not->toBeNull()
        ->and((float) $row->weighted_percent)->toBe(90.0)
        ->and($row->grade)->toBe('A')
        ->and($row->rank)->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.gradebook.index', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('missing_weights', false)
            ->where('rows.0.term.grade', 'A')
            ->where('rows.0.term.rank', 1)
            ->where('rows.0.term.weighted_percent', fn ($percent) => (float) $percent === 90.0)
        );

    $cards = app(GenerateReportCardsAction::class)->execute(
        $class->id,
        $term->id,
        null,
        'en',
        $admin->id,
        false,
    );

    expect($cards)->toHaveCount(1);
    $html = app(DocumentRendererInterface::class)->render(
        'report-card',
        app(\App\Domains\ExamsGrades\Actions\AssembleReportCardDataAction::class)->execute($cards->first()->fresh(['template', 'comments'])),
    );

    // The end of the chain, and the reason this mattered: the report card a
    // parent downloads. It used to read 36 and E for a child who scored 90/100.
    expect($html)->toContain('Fatima Yoosuf')
        ->and($html)->toContain('90')
        ->and($html)->toContain('>A<')
        ->and($html)->toContain('>1<')
        ->and($html)->not->toContain('<td></td>');
});
