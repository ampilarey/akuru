<?php

use App\Domains\Academics\Models\PlanTopic;
use App\Domains\ExamsGrades\Actions\ListStandardsCoverageAction;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveStandardAction;
use App\Domains\ExamsGrades\Actions\TagStandardAction;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\ExamsGrades\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates a standard hierarchy and reports coverage for exams and topics', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $subject = makeSubject();
    $parent = app(SaveStandardAction::class)->execute([
        'subject_id' => $subject->id,
        'code' => 'ARB.1',
        'title' => 'Reading',
    ]);
    $child = app(SaveStandardAction::class)->execute([
        'subject_id' => $subject->id,
        'code' => 'ARB.1.1',
        'title' => 'Short vowels',
        'parent_id' => $parent->id,
    ]);

    $class = makeClass($year);
    $exam = app(SaveExamAction::class)->execute([
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'exam_type_id' => ExamType::query()->where('code', ExamTypeCode::Quiz)->value('id'),
        'name' => 'Quiz 1',
        'exam_date' => '2026-08-10',
        'max_marks' => 10,
    ]);

    $plan = makeCoursePlan(['year' => $year, 'subject_id' => $subject->id, 'classroom_id' => $class->id, 'term_id' => $term->id]);
    $topic = PlanTopic::query()->create([
        'course_plan_id' => $plan->id,
        'order' => 1,
        'title' => 'Fatha',
        'is_completed' => false,
    ]);

    app(TagStandardAction::class)->execute([
        'standard_id' => $child->id,
        'taggable_type' => 'exam',
        'taggable_id' => $exam->id,
    ]);
    app(TagStandardAction::class)->execute([
        'standard_id' => $child->id,
        'taggable_type' => 'plan_topic',
        'taggable_id' => $topic->id,
    ]);

    expect(fn () => app(SaveStandardAction::class)->execute([
        'code' => 'ARB.1.1',
        'title' => 'Duplicate',
    ]))->toThrow(ValidationException::class);

    $coverage = app(ListStandardsCoverageAction::class)->execute($subject->id, $term->id);
    $row = $coverage->firstWhere('id', $child->id);
    expect($row['exams_tagged'])->toBe(1)
        ->and($row['topics_tagged'])->toBe(1)
        ->and($row['covered'])->toBeTrue()
        ->and($coverage->firstWhere('id', $parent->id)['covered'])->toBeFalse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.standards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Standards/Index')
            ->has('standards', 2)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.standards.export', ['subject_id' => $subject->id]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('ARB.1.1')->toContain('Short vowels');
    expect(Standard::query()->where('parent_id', $parent->id)->count())->toBe(1);
});
