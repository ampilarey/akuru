<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveReportCardTemplateAction;
use App\Domains\ExamsGrades\Actions\SaveStandardAction;
use App\Domains\ExamsGrades\Enums\AwardLevel;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Enums\GradeScaleType;
use App\Domains\ExamsGrades\Enums\ReportCardCommentType;
use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Models\Award;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The school office's exams and grades screens in Dhivehi and Arabic
 * (BACKLOG C21, slices EG1 and EG2, STATUS §5qj and §5qk).
 *
 * The exam schedule, an exam's marks, the gradebook, the assessment weights,
 * the grade scales and the exam types read no phrase book. Every word on
 * them was English; an exam's state, an exam type's code and a grade
 * scale's kind were printed as codes (*marks_entry*, *percentage_bands*);
 * a subject and an exam type read by their English names though the school
 * names them in three languages; the weights screen printed ids
 * (*year 3 / class — / subject —*) and the stored JSON; and so was
 * everything the server said — thirteen saved messages and forty-two
 * refusals, among them a clash (*This class already has 2 exam(s) on that
 * date (max 2).*) and a move the exam's state does not allow (*Cannot move
 * from scheduled to locked.*, with both states as codes).
 *
 * EG2 adds the awards, competencies, standards, report cards and their
 * templates. An award's level, a report card's state and a template's
 * sections were printed as codes (*ready*, *grades_table*); an award and a
 * standard read by their English titles though the school writes them in
 * three languages; the standards screen offered the exams for a plan topic,
 * so a topic was tagged by an exam's id; and the server's eleven saved
 * messages and twenty-three refusals were English.
 */
uses(RefreshDatabase::class);

/** The exams screens in three languages; every phrase on them is `t.key || 'English'`, from the `exams` book. */
function examsScreens(): array
{
    return [
        'ExamsGrades/Exams/Index', 'ExamsGrades/Marks/Show', 'ExamsGrades/Gradebook/Index',
        'ExamsGrades/Weights/Index', 'ExamsGrades/Scales/Index', 'ExamsGrades/Types/Index',
        // EG2.
        'ExamsGrades/Awards/Index', 'ExamsGrades/Competencies/Index', 'ExamsGrades/Standards/Index',
        'ExamsGrades/ReportCards/Index', 'ExamsGrades/ReportCards/Templates',
    ];
}

/** Where the server writes what those screens say. */
function examsServerFiles(): array
{
    return [
        'app/Domains/ExamsGrades/Http/Controllers/ExamController.php',
        'app/Domains/ExamsGrades/Http/Controllers/ExamMarkController.php',
        'app/Domains/ExamsGrades/Http/Controllers/GradebookController.php',
        'app/Domains/ExamsGrades/Http/Controllers/WeightSchemeController.php',
        'app/Domains/ExamsGrades/Http/Controllers/GradeScaleController.php',
        'app/Domains/ExamsGrades/Http/Controllers/ExamTypeController.php',
        'app/Domains/ExamsGrades/Actions/SaveExamAction.php',
        'app/Domains/ExamsGrades/Actions/BulkScheduleExamsAction.php',
        'app/Domains/ExamsGrades/Actions/TransitionExamStatusAction.php',
        'app/Domains/ExamsGrades/Actions/SaveExamMarkAction.php',
        'app/Domains/ExamsGrades/Actions/ImportExamMarksAction.php',
        'app/Domains/ExamsGrades/Actions/SaveWeightSchemeAction.php',
        'app/Domains/ExamsGrades/Actions/SaveGradeScaleAction.php',
        'app/Domains/ExamsGrades/Actions/SaveExamTypeAction.php',
        // EG2.
        'app/Domains/ExamsGrades/Http/Controllers/AwardController.php',
        'app/Domains/ExamsGrades/Http/Controllers/CompetencyController.php',
        'app/Domains/ExamsGrades/Http/Controllers/ReportCardController.php',
        'app/Domains/ExamsGrades/Http/Controllers/ReportCardTemplateController.php',
        'app/Domains/ExamsGrades/Http/Controllers/StandardController.php',
        'app/Domains/ExamsGrades/Actions/SaveAwardAction.php',
        'app/Domains/ExamsGrades/Actions/IssueStudentAwardsAction.php',
        'app/Domains/ExamsGrades/Actions/SaveCompetencyAction.php',
        'app/Domains/ExamsGrades/Actions/SaveCompetencyAssessmentAction.php',
        'app/Domains/ExamsGrades/Actions/GenerateReportCardsAction.php',
        'app/Domains/ExamsGrades/Actions/PublishReportCardsAction.php',
        'app/Domains/ExamsGrades/Actions/SaveReportCardCommentAction.php',
        'app/Domains/ExamsGrades/Actions/SaveReportCardTemplateAction.php',
        'app/Domains/ExamsGrades/Actions/SaveStandardAction.php',
        'app/Domains/ExamsGrades/Actions/TagStandardAction.php',
    ];
}

function examsBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/exams.php");
}

it('keys every string on the exams screens in three languages', function () {
    [$en, $dv, $ar] = [examsBook('en'), examsBook('dv'), examsBook('ar')];

    foreach (examsScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: exams.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: exams.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: exams.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: exams.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "exams.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "exams.{$key} is English in Arabic");
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every field the exams screens post, so a refusal by Laravel’s own rules reads whole in Dhivehi and Arabic', function () {
    $dhivehi = (require resource_path('lang/dv/validation.php'))['attributes'];
    $arabic = (require resource_path('lang/ar/validation.php'))['attributes'];

    $fields = [];
    foreach (array_filter(examsServerFiles(), fn (string $file) => str_contains($file, '/Controllers/')) as $file) {
        preg_match_all("/'([a-z_]+(?:\\.\\*(?:\\.[a-z_]+)?)?)' => \\[(?=[^\\]]*'(?:required|nullable|sometimes|integer|string|array|boolean|date|numeric)')/", file_get_contents(base_path($file)), $found);
        $fields = [...$fields, ...$found[1]];
    }
    expect($fields)->toContain('student_ids', 'award_id', 'standard_id', 'taggable_id', 'exam_type_id', 'rows.*.marks');

    foreach (array_unique($fields) as $field) {
        expect(array_key_exists($field, $dhivehi))->toBeTrue("{$field} has no Dhivehi name")
            ->and(array_key_exists($field, $arabic))->toBeTrue("{$field} has no Arabic name");
    }

    // An award issued to nobody: it read *student ids ބޭނުންވޭ.*
    app()->setLocale('dv');
    expect(validator(['student_ids' => []], ['student_ids' => ['required', 'array']])->errors()->first('student_ids'))->toBe('ދަރިވަރުން ބޭނުންވޭ.');
    app()->setLocale('ar');
    expect(validator(['rows' => [['marks' => 'x']]], ['rows.*.marks' => ['numeric']])->errors()->first('rows.0.marks'))->not->toMatch('/[A-Za-z]/');
});

it('names every code the exams screens show, in all three languages', function () {
    $codes = [
        ...array_map(fn ($case) => 'exam_status_'.$case->value, ExamStatus::cases()),
        ...array_map(fn ($case) => 'exam_type_code_'.$case->value, ExamTypeCode::cases()),
        ...array_map(fn ($case) => 'scale_type_'.$case->value, GradeScaleType::cases()),
        // EG2: an award's level, a report card's state, who wrote a comment,
        // a template's sections, what a standard is tagged against, and the
        // language a card or a transcript is made in.
        ...array_map(fn ($case) => 'award_level_'.$case->value, AwardLevel::cases()),
        ...array_map(fn ($case) => 'report_card_status_'.$case->value, ReportCardStatus::cases()),
        ...array_map(fn ($case) => 'comment_type_'.$case->value, ReportCardCommentType::cases()),
        ...array_map(fn (string $section) => 'report_section_'.$section, SaveReportCardTemplateAction::SECTIONS),
        'taggable_exam', 'taggable_plan_topic', 'language_en', 'language_dv', 'language_ar',
    ];

    foreach ($codes as $key) {
        expect(trans("exams.{$key}", [], 'en'))->not->toBe("exams.{$key}", "exams.{$key} has no English")
            ->and(trans("exams.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "exams.{$key} in Dhivehi")
            ->and(trans("exams.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "exams.{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the exams screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (examsServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(examsServerFiles());
    expect($keys)->toContain('exams.flash_exam_scheduled', 'exams.flash_exams_scheduled', 'exams.flash_marks_imported', 'exams.error_same_day', 'exams.error_cannot_move', 'exams.error_marks_above_max', 'exams.error_weights_sum', 'exams.error_band_grade', 'exams.error_type_code_exists',
        'exams.flash_cards_queued', 'exams.error_none_ready', 'exams.error_pick_student', 'exams.error_section_required', 'exams.error_standard_code_exists', 'exams.error_topic_missing');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
    // A move the exam's state does not allow names both states in the
    // page's language, not by their codes.
    expect(__('exams.error_cannot_move', ['from' => __('exams.exam_status_scheduled', [], 'dv'), 'to' => __('exams.exam_status_locked', [], 'dv')], 'dv'))->not->toMatch('/[A-Za-z]{3,}/');
});

it('gives the six exam types a school starts with a Dhivehi and an Arabic name, and keeps one the office typed', function () {
    expect(ExamType::query()->count())->toBe(count(ExamTypeCode::cases()))
        ->and(ExamType::query()->get(['code', 'name_dhivehi', 'name_arabic'])->every(
            fn (ExamType $type) => preg_match('/\p{Thaana}/u', (string) $type->name_dhivehi) === 1 && preg_match('/\p{Arabic}/u', (string) $type->name_arabic) === 1,
        ))->toBeTrue();

    $quiz = ExamType::query()->where('code', ExamTypeCode::Quiz)->sole();
    $quiz->update(['name_dhivehi' => 'ކުޑަ އިމްތިޙާން']);
    (require base_path('database/migrations/2026_10_10_000001_exam_type_names_in_dhivehi_and_arabic.php'))->up();

    expect($quiz->refresh()->name_dhivehi)->toBe('ކުޑަ އިމްތިޙާން');
});

it('serves the exams screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 6', 'A');
    $subject = makeSubject();
    $type = ExamType::query()->where('code', ExamTypeCode::Final)->sole();
    $office = actingPeopleAdmin(['exams.manage']);
    $dv = examsBook('dv');
    $exam = app(SaveExamAction::class)->execute([
        'academic_year_id' => $year->id, 'term_id' => $term->id, 'class_id' => $class->id,
        'subject_id' => $subject->id, 'exam_type_id' => $type->id, 'name' => 'Term 1 Final',
        'exam_date' => '2026-08-24', 'max_marks' => 50,
    ]);

    app()->setLocale('dv');
    foreach ([
        ['exams.index', [], 'ExamsGrades/Exams/Index', 'exams_title'],
        ['exams.marks.show', [$exam->id], 'ExamsGrades/Marks/Show', 'marks_title'],
        ['exams.gradebook.index', [], 'ExamsGrades/Gradebook/Index', 'gradebook_title'],
        ['exams.weights.index', [], 'ExamsGrades/Weights/Index', 'weights_title'],
        ['exams.scales.index', [], 'ExamsGrades/Scales/Index', 'scales_title'],
        ['exams.types.index', [], 'ExamsGrades/Types/Index', 'types_title'],
    ] as [$route, $parameters, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route, $parameters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // Saved, in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.store'), [
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'class_id' => $class->id,
            'subject_id' => $subject->id, 'exam_type_id' => $type->id, 'name' => 'Term 1 Quiz',
            'exam_date' => '2026-08-25', 'max_marks' => 20,
        ])
        ->assertSessionHas('success', $dv['flash_exam_scheduled']);

    // A move the exam's state does not allow names both states in Dhivehi;
    // it named them by their codes, in English.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.transition', $exam->id), ['status' => ExamStatus::Locked->value])
        ->assertSessionHasErrors(['status' => __('exams.error_cannot_move', ['from' => $dv['exam_status_scheduled'], 'to' => $dv['exam_status_locked']], 'dv')]);

    // A mark refused while the exam is not taking marks, in Dhivehi.
    $pupil = makeStudent(['first_name' => 'Hawwa', 'last_name' => 'Ibrahim']);
    app(AssignStudentToClassAction::class)->execute($class, $pupil->id);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('exams.marks.update', $exam->id), ['student_id' => $pupil->id, 'marks' => 40])
        ->assertSessionHasErrors(['status' => $dv['error_marks_closed']]);

    // Weights that do not add to 100 say what they came to; a scale's band
    // with no grade, and an exam type code taken twice, are refused — all in
    // Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.weights.store'), ['academic_year_id' => $year->id, 'weights' => [(string) $type->id => 60]])
        ->assertSessionHasErrors(['weights' => __('exams.error_weights_sum', ['sum' => 60], 'dv')]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.scales.store'), ['name' => 'Bands', 'type' => GradeScaleType::PercentageBands->value, 'bands' => [['min' => 50, 'grade' => '']]])
        ->assertSessionHasErrors(['bands' => $dv['error_band_grade']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.types.store'), ['name' => 'Second final', 'code' => ExamTypeCode::Final->value, 'default_weight' => 10])
        ->assertSessionHasErrors(['code' => $dv['error_type_code_exists']]);
});

it('serves the awards, competencies, standards and report cards screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year, 'Grade 6', 'A');
    $subject = makeSubject();
    $office = actingPeopleAdmin(['exams.manage']);
    $dv = examsBook('dv');

    app()->setLocale('dv');
    foreach ([
        ['exams.awards.index', 'ExamsGrades/Awards/Index', 'awards_title'],
        ['exams.competencies.index', 'ExamsGrades/Competencies/Index', 'competencies_title'],
        ['exams.standards.index', 'ExamsGrades/Standards/Index', 'standards_title'],
        ['exams.report-cards.index', 'ExamsGrades/ReportCards/Index', 'reportcards_title'],
        ['exams.report-templates.index', 'ExamsGrades/ReportCards/Templates', 'templates_title'],
    ] as [$route, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // An award is saved, in Dhivehi, with its Dhivehi title; issued to no
    // pupil, it is refused, in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.awards.store'), ['title' => 'Best reader', 'title_dhivehi' => 'އެންމެ މޮޅު ކިޔުންތެރިޔާ', 'level' => AwardLevel::School->value])
        ->assertSessionHas('success', $dv['flash_award_saved']);
    $award = Award::query()->where('title', 'Best reader')->sole();
    expect($award->title_dhivehi)->toBe('އެންމެ މޮޅު ކިޔުންތެރިޔާ');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.awards.issue'), ['award_id' => $award->id, 'student_ids' => [0], 'academic_year_id' => $year->id, 'awarded_date' => '2026-09-01'])
        ->assertSessionHasErrors(['student_ids' => $dv['error_pick_student']]);

    // Nothing ready to publish, a template with no section it knows, a
    // standard code taken twice and a plan topic that is not there are
    // refused — all in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.report-cards.publish'), ['class_id' => $class->id, 'term_id' => $term->id])
        ->assertSessionHasErrors(['status' => $dv['error_none_ready']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.report-templates.store'), ['name' => 'Short', 'sections' => ['no_such_section']])
        ->assertSessionHasErrors(['sections' => $dv['error_section_required']]);
    $standard = app(SaveStandardAction::class)->execute(['code' => 'MATH.1', 'title' => 'Counting', 'subject_id' => $subject->id]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.standards.store'), ['code' => 'MATH.1', 'title' => 'Counting again'])
        ->assertSessionHasErrors(['code' => $dv['error_standard_code_exists']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('exams.standards.tag'), ['standard_id' => $standard->id, 'taggable_type' => 'plan_topic', 'taggable_id' => 999999])
        ->assertSessionHasErrors(['taggable_id' => $dv['error_topic_missing']]);
});
