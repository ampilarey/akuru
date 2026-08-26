<?php

use App\Domains\ExamsGrades\Actions\ResolveWeightSchemeAction;
use App\Domains\ExamsGrades\Actions\SaveExamTypeAction;
use App\Domains\ExamsGrades\Actions\SaveGradeScaleAction;
use App\Domains\ExamsGrades\Actions\SaveWeightSchemeAction;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Enums\GradeScaleType;
use App\Domains\ExamsGrades\Models\AssessmentWeightScheme;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\ExamsGrades\Models\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('seeds a default grade scale and the six exam type codes', function () {
    expect(GradeScale::query()->where('is_default', true)->count())->toBe(1)
        ->and(ExamType::query()->count())->toBe(6)
        ->and(ExamType::query()->pluck('code')->map(fn ($code) => $code instanceof ExamTypeCode ? $code->value : (string) $code)->sort()->values()->all())
        ->toBe([
            ExamTypeCode::Assignment->value,
            ExamTypeCode::Final->value,
            ExamTypeCode::Midterm->value,
            ExamTypeCode::Oral->value,
            ExamTypeCode::Practical->value,
            ExamTypeCode::Quiz->value,
        ]);
});

it('creates updates and exports grade scales and keeps a single default', function () {
    $admin = actingPeopleAdmin(['exams.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.scales.store'), [
            'name' => 'Letters',
            'type' => GradeScaleType::Letter->value,
            'bands' => [
                ['grade' => 'A', 'point' => 4, 'descriptor_en' => 'Top'],
            ],
            'is_default' => true,
            'active' => true,
        ])
        ->assertRedirect();

    expect(GradeScale::query()->where('is_default', true)->count())->toBe(1)
        ->and(GradeScale::query()->where('name', 'Letters')->sole()->is_default)->toBeTrue();

    $scale = GradeScale::query()->where('name', 'Letters')->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.scales.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Scales/Index')
            ->has('scales')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.scales.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Letters');

    app(SaveGradeScaleAction::class)->execute([
        'name' => 'Letters',
        'type' => GradeScaleType::Letter->value,
        'bands' => [['grade' => 'A', 'point' => 4]],
        'is_default' => false,
        'active' => true,
    ], $scale);
});

it('lists and exports exam types for an admin', function () {
    $admin = actingPeopleAdmin(['exams.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Types/Index')
            ->has('types', 6)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.types.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Midterm')->toContain('Final');
});

it('rejects a second exam type with the same code', function () {
    expect(fn () => app(SaveExamTypeAction::class)->execute([
        'name' => 'Another midterm',
        'code' => ExamTypeCode::Midterm->value,
        'default_weight' => 10,
    ]))->toThrow(ValidationException::class);
});

it('rejects weight schemes that do not sum to 100', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $midterm = ExamType::query()->where('code', ExamTypeCode::Midterm)->sole();

    expect(fn () => app(SaveWeightSchemeAction::class)->execute([
        'academic_year_id' => $year->id,
        'weights' => [$midterm->id => 40],
    ]))->toThrow(ValidationException::class);
});

it('resolves weight schemes subject then class then year', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $subject = makeSubject();
    $types = ExamType::query()->orderBy('id')->get();
    $yearWeights = [];
    $classWeights = [];
    $subjectWeights = [];
    foreach ($types as $index => $type) {
        $yearWeights[$type->id] = $index === 0 ? 100 : 0;
        $classWeights[$type->id] = $index === 1 ? 100 : 0;
        $subjectWeights[$type->id] = $index === 2 ? 100 : 0;
    }

    $yearScheme = app(SaveWeightSchemeAction::class)->execute([
        'academic_year_id' => $year->id,
        'weights' => $yearWeights,
    ]);
    $classScheme = app(SaveWeightSchemeAction::class)->execute([
        'academic_year_id' => $year->id,
        'class_id' => $class->id,
        'weights' => $classWeights,
    ]);
    $subjectScheme = app(SaveWeightSchemeAction::class)->execute([
        'academic_year_id' => $year->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'weights' => $subjectWeights,
    ]);

    $resolve = app(ResolveWeightSchemeAction::class);

    expect($resolve->execute($year->id, $class->id, $subject->id)?->id)->toBe($subjectScheme->id)
        ->and($resolve->execute($year->id, $class->id, null)?->id)->toBe($classScheme->id)
        ->and($resolve->execute($year->id, null, null)?->id)->toBe($yearScheme->id)
        ->and($resolve->execute($year->id, $class->id + 99, $subject->id + 99)?->id)->toBe($yearScheme->id);
});

it('lists and exports weight schemes for an admin', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $types = ExamType::query()->orderBy('id')->get();
    $weights = [];
    foreach ($types as $index => $type) {
        $weights[$type->id] = $index === 0 ? 100 : 0;
    }
    AssessmentWeightScheme::query()->create([
        'academic_year_id' => $year->id,
        'weights' => $weights,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.weights.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Weights/Index')
            ->has('schemes', 1)
            ->where('resolve.scheme.academic_year_id', $year->id)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.weights.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain((string) $year->id);
});

it('persists a year scheme from the weights HTTP form using exam-type defaults', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['name' => 'Pilot 2026', 'is_current' => true, 'status' => 'active']);
    $weights = ExamType::query()
        ->get()
        ->mapWithKeys(fn (ExamType $type) => [(string) $type->id => (int) $type->default_weight])
        ->all();

    expect(array_sum($weights))->toBe(100)
        ->and(AssessmentWeightScheme::query()->count())->toBe(0);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.weights.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Weights/Index')
            ->has('examTypes', 6)
            ->where('resolve.scheme', null)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.weights.store'), [
            'academic_year_id' => $year->id,
            'class_id' => '',
            'subject_id' => '',
            'weights' => $weights,
        ])
        ->assertRedirect();

    expect(AssessmentWeightScheme::query()->count())->toBe(1);

    $scheme = AssessmentWeightScheme::query()->sole();
    expect($scheme->academic_year_id)->toBe($year->id)
        ->and($scheme->class_id)->toBeNull()
        ->and($scheme->subject_id)->toBeNull()
        ->and(array_sum($scheme->weights))->toBe(100);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.weights.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('schemes', 1)
            ->where('resolve.scheme.academic_year_id', $year->id)
            ->where('resolve.scheme.class_id', null)
        );
});

it('rejects an HTTP weight scheme whose percents sum to zero', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $zeros = ExamType::query()
        ->get()
        ->mapWithKeys(fn (ExamType $type) => [(string) $type->id => 0])
        ->all();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('exams.weights.index'))
        ->post(route('exams.weights.store'), [
            'academic_year_id' => $year->id,
            'weights' => $zeros,
        ])
        ->assertSessionHasErrors('weights');

    expect(AssessmentWeightScheme::query()->count())->toBe(0);
});
