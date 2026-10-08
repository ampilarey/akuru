<?php

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Actions\NormalizeTextAnswerAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Quran\Enums\MemorizationStatus;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentStatus;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentType;
use App\Domains\Courses\Components\Quran\Enums\QuranLaneResult;
use App\Domains\Courses\Components\Quran\Enums\QuranMistakeSeverity;
use App\Domains\Courses\Components\Quran\Enums\QuranMistakeType;
use App\Domains\Courses\Components\Quran\Enums\QuranRevisionResult;
use App\Domains\Courses\Components\Quran\Enums\QuranSessionOverallStatus;
use App\Domains\Courses\Components\Quran\Enums\RecitationSubmissionStatus;
use App\Domains\Courses\Components\Quran\Enums\RevisionScheduleStatus;
use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\Surah;
use App\Domains\Courses\Enums\ActivityPattern;
use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Enums\AssessmentType;
use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Enums\CourseReviewDecision;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\LessonStatus;
use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Enums\QuestionType;
use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Enums\Hifz\HifzMilestoneStatus;
use App\Enums\Hifz\HifzMilestoneType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * The course-building screens in Dhivehi and Arabic (slices CT1–CT3, STATUS
 * §5ok on). The owner, 2026-10-08: "Leave everything from my side. Continue
 * from ur side" — and the first thing on this side was that the screens
 * teachers build courses on were English only.
 *
 * Every string on a translated screen is `t.key || 'English'`. This reads the
 * screen's source and holds three things: the key is in the `teach` book in
 * all three languages, the book's English is exactly the screen's fallback (so
 * an English page reads the same either way), and no bare English is left in
 * a text node, a placeholder or a label.
 */
uses(RefreshDatabase::class);

/**
 * The screens translated so far, and the phrase book each reads; each slice
 * adds its own. Deleted courses is a screen of the website's course list, so
 * it reads that list's book (slice CT4). The learner's Qur'an page reads the
 * `learn` book the shell shares with every learner screen (slice CT5b).
 */
function translatedCourseScreens(): array
{
    return [
        'Courses/Catalog/Index' => 'teach',
        'Courses/Catalog/Outline' => 'teach',
        'Courses/Catalog/Rubrics' => 'teach',
        'Courses/Catalog/Activities' => 'teach',
        'Courses/Catalog/Assessments' => 'teach',
        'Courses/Catalog/Questions' => 'teach',
        'Courses/Catalog/Glossary' => 'teach',
        'Courses/Catalog/Certificates' => 'teach',
        'Courses/Catalog/Reviews' => 'teach',
        'Courses/Catalog/CompletionReports' => 'teach',
        'Courses/Catalog/Reports' => 'teach',
        'Courses/Taxonomy/Subjects' => 'teach',
        'Courses/Taxonomy/Levels' => 'teach',
        'Courses/Taxonomy/Audiences' => 'teach',
        'Courses/DeletedCourses' => 'admin',
        'Courses/Teach/QuranAssignments' => 'teach',
        'Courses/Teach/QuranMilestones' => 'teach',
        'Courses/Teach/QuranSessionSheet' => 'teach',
        'Courses/Teach/RecitationQueue' => 'teach',
        'Courses/Catalog/QuranOversight' => 'teach',
        'Courses/Catalog/QuranReference' => 'teach',
        'Courses/Quran/Mushafs/Index' => 'teach',
        'Courses/Quran/Mushafs/Create' => 'teach',
        'Courses/Quran/Mushafs/Show' => 'teach',
        'Courses/Quran/Pages/Show' => 'teach',
        'Courses/Learn/Quran' => 'learn',
    ];
}

/** The same in every language, on purpose. */
function sameInEveryLanguage(): array
{
    return ['block_pdf'];
}

function teachBook(string $locale, string $book = 'teach'): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

/**
 * Fields a screen reader has no name for: no aria-label, no id for a label to
 * point at, and not inside a label.
 */
function unnamedFields(string $source): array
{
    preg_match_all('/<(select|input|textarea)\b(.*?)(\/>|<option|\.map\(|<\/select>)/s', $source, $fields, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $unnamed = [];
    foreach ($fields as $field) {
        [$attributes, $at] = $field[2];
        $before = substr($source, max(0, $at - 300), min(300, $at));
        $inLabel = strrpos($before, '<label') !== false && strrpos($before, '<label') > (int) strrpos($before, '</label>');
        if (! str_contains($attributes, 'aria-label=') && ! preg_match('/\sid=/', $attributes) && ! $inLabel && ! str_contains($attributes, 'type="hidden"')) {
            $unnamed[] = substr(preg_replace('/\s+/', ' ', trim($attributes)), 0, 80);
        }
    }

    return $unnamed;
}

it('keys every string on the translated course screens in three languages', function () {
    foreach (translatedCourseScreens() as $screen => $book) {
        [$en, $dv, $ar] = [teachBook('en', $book), teachBook('dv', $book), teachBook('ar', $book)];
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: {$book}.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: {$book}.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: {$book}.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: {$book}.{$key} says something else in English than the screen");
            if (! in_array($key, sameInEveryLanguage(), true)) {
                expect($dv[$key])->not->toBe($en[$key], "{$book}.{$key} is English in Dhivehi")
                    ->and($ar[$key])->not->toBe($en[$key], "{$book}.{$key} is English in Arabic");
            }
        }

        // No bare English: a text node (a word or an abbreviation like LTR),
        // a placeholder or a label written out.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every status, decision, unlock rule, block type, pattern, assessment and question type the server sends', function () {
    $book = fn (string $locale) => teachBook($locale);
    $needed = [
        ...array_map(fn ($case) => 'decision_'.$case->value, CourseReviewDecision::cases()),
        ...array_map(fn ($case) => 'unlock_'.$case->value, UnlockMode::cases()),
        ...array_map(fn ($case) => 'workflow_'.$case->value, CourseWorkflowStatus::cases()),
        ...array_map(fn ($case) => 'status_'.$case->value, [...LessonStatus::cases(), ...ModuleStatus::cases()]),
        ...array_map(fn ($case) => 'block_'.$case->value, ContentBlockType::cases()),
        ...array_map(fn ($case) => 'pattern_'.$case->value, ActivityPattern::cases()),
        ...array_map(fn ($case) => 'assessment_type_'.$case->value, AssessmentType::cases()),
        ...array_map(fn ($case) => 'status_'.$case->value, AssessmentStatus::cases()),
        ...array_map(fn ($case) => 'question_type_'.$case->value, QuestionType::cases()),
        ...array_map(fn ($flag) => 'flag_'.$flag, NormalizeTextAnswerAction::flags()),
        ...array_map(fn ($mode) => 'mode_'.$mode, NormalizeTextAnswerAction::modes()),
        ...array_map(fn ($difficulty) => 'difficulty_'.$difficulty, ['easy', 'medium', 'hard']),
        ...array_map(fn ($case) => 'certificate_kind_'.$case->value, CertificateKind::cases()),
        ...array_map(fn ($status) => 'cert_status_'.$status, ['issued', 'revoked']),
        ...array_map(fn ($kind) => 'review_kind_'.$kind, ['activity', 'assessment']),
        // Every value `course_enrollments.status` may hold (slice CT4).
        ...array_map(fn ($status) => 'enrol_status_'.$status, ['pending', 'approved', 'rejected', 'active', 'completed', 'cancelled', 'suspended']),
    ];

    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach ($needed as $key) {
            expect(array_key_exists($key, $book($locale)))->toBeTrue("teach.{$key} is missing in {$locale}");
        }
    }
});

it('serves the catalog and the outline in Dhivehi, and says what was saved in the language of the page', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Fiqh one',
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Index')
            ->where('t.catalog_title', trans('teach.catalog_title', [], 'dv'))
            ->where('t.workflow_draft', 'ޑްރާފްޓް'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.outline', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Outline')
            ->where('t.outline_save_module', trans('teach.outline_save_module', [], 'dv')));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.modules.store', $course->id), ['title' => 'Purity'])
        ->assertSessionHas('success', trans('teach.flash_module_saved', [], 'dv'));
    expect(CourseModule::query()->where('course_id', $course->id)->value('title'))->toBe('Purity');

    app()->setLocale('en');
    expect(__('teach.flash_module_saved'))->toBe('Module saved.');
});

it('names the seeded subjects in Dhivehi and Arabic for the catalog, and keeps a name somebody typed', function () {
    $subjects = CourseSubject::query()->get(['slug', 'name_en', 'name_dv', 'name_ar']);
    expect($subjects)->toHaveCount(17);
    foreach ($subjects as $subject) {
        expect($subject->name_dv)->not->toBeEmpty("{$subject->slug} has no Dhivehi name")
            ->and($subject->name_ar)->not->toBeEmpty("{$subject->slug} has no Arabic name")
            ->and($subject->name_dv)->not->toBe($subject->name_en);
    }

    $this->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin(['courses.manage']))
        ->get(route('catalog.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('subjects', fn ($rows) => collect($rows)->firstWhere('slug', 'fiqh')['name_ar'] === 'الفقه'));

    CourseSubject::query()->where('slug', 'hadith')->update(['name_dv' => 'ޙަދީޘް ދިރާސާ', 'name_ar' => null]);
    (require database_path('migrations/2026_10_08_000001_course_subject_names_in_dhivehi_and_arabic.php'))->up();
    expect(CourseSubject::query()->where('slug', 'hadith')->first())
        ->name_dv->toBe('ޙަދީޘް ދިރާސާ')
        ->name_ar->toBe('الحديث');
});

it('serves activities, assessments and the question bank in Dhivehi, names what they are sent, and says what was saved in Dhivehi', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Tajweed one',
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $dv = teachBook('dv');
    $named = fn (string $prefix) => fn ($values) => collect($values)->every(fn ($value) => isset($dv[$prefix.(is_array($value) ? $value['value'] : $value)]));

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.activities.index', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Activities')
            ->where('t.activities_save', $dv['activities_save'])
            ->where('patterns', $named('pattern_'))
            ->where('skills', $named('skill_')));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.assessments.index', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Assessments')
            ->where('t.assess_save', $dv['assess_save'])
            ->where('types', $named('assessment_type_')));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.questions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Questions')
            ->where('t.questions_save', $dv['questions_save'])
            ->where('types', $named('question_type_'))
            ->where('normalizationFlags', $named('flag_'))
            ->where('normalizationModes', $named('mode_')));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.activities.store', $course->id), [
            'title' => 'Pick the letter',
            'pattern' => 'selection',
            'activity_type' => 'multiple_choice',
            'max_score' => 1,
            'data' => json_encode(['prompt' => 'Which?', 'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']], 'correct_ids' => ['a']]),
        ])
        ->assertSessionHas('success', $dv['flash_activity_saved']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.assessments.store', $course->id), ['title' => 'Quiz one', 'status' => 'draft'])
        ->assertSessionHas('success', $dv['flash_assessment_saved']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.questions.store'), [
            'question_type' => 'mcq_single',
            'question_text' => 'Is it a letter?',
            'options' => json_encode([['id' => 'a', 'label' => 'Yes'], ['id' => 'b', 'label' => 'No']]),
            'correct_answer' => json_encode(['a']),
        ])
        ->assertSessionHas('success', $dv['flash_question_saved']);

    app()->setLocale('en');
    expect(__('teach.flash_question_saved'))->toBe('Question saved.');
});

it('serves the glossary, certificates and the marking queue in Dhivehi, and says what was saved in Dhivehi', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $dv = teachBook('dv');
    $named = fn (string $prefix) => fn ($values) => collect($values)->every(fn ($value) => isset($dv[$prefix.$value['value']]));

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.glossary.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Glossary')
            ->where('t.glossary_save', $dv['glossary_save'])
            ->where('levels', fn ($levels) => collect($levels)->every(fn ($level) => filled($level['name_dv']) && filled($level['name_ar']))));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.certificates.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Certificates')
            ->where('t.cert_save_template', $dv['cert_save_template'])
            ->where('kinds', $named('certificate_kind_')));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.reviews.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Reviews')
            ->where('t.review_score_release', $dv['review_score_release'])
            ->missing('teach'));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.glossary.store'), ['term' => 'Tajweed'])
        ->assertSessionHas('success', $dv['flash_term_saved']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.certificates.store'), ['name' => 'Completion', 'kind' => 'course_completion'])
        ->assertSessionHas('success', $dv['flash_template_saved']);

    app()->setLocale('en');
    expect(__('teach.flash_certificate_issued', ['number' => 'AK-1']))->toBe('Certificate issued: AK-1')
        ->and(__('teach.review_retry', ['title' => 'Choose meaning']))->toBe('Retry Choose meaning');
});

it('serves the reports, the taxonomy and deleted courses in Dhivehi, and says what was saved in Dhivehi', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $dv = teachBook('dv');

    app()->setLocale('dv');
    foreach ([
        'catalog.reports.index' => ['Courses/Catalog/Reports', 'reports_total_students'],
        'catalog.reports.completions' => ['Courses/Catalog/CompletionReports', 'completion_roster'],
        'catalog.subjects.index' => ['Courses/Taxonomy/Subjects', 'taxonomy_save_subject'],
        'catalog.levels.index' => ['Courses/Taxonomy/Levels', 'taxonomy_save_level'],
        'catalog.audiences.index' => ['Courses/Taxonomy/Audiences', 'taxonomy_save_audience'],
    ] as $route => [$component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($admin)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    foreach ([
        'catalog.subjects.store' => 'flash_subject_saved',
        'catalog.levels.store' => 'flash_level_saved',
        'catalog.audiences.store' => 'flash_audience_saved',
    ] as $route => $flash) {
        $this->withoutLocalizationMiddleware()->actingAs($admin)
            ->post(route($route), ['name_en' => "CT4 {$flash}", 'name_dv' => 'ސީޓީ ހަތަރު'])
            ->assertSessionHas('success', $dv[$flash]);
    }

    // Deleted courses is the website course list's screen, and reads its book.
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.courses.deleted'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/DeletedCourses')
            ->where('t.courses_deleted_title', trans('admin.courses_deleted_title', [], 'dv')));

    // Every table that keeps a deleted course is named on the screen.
    foreach (array_keys((new ReflectionClassConstant(DeleteCourseAction::class, 'DEPENDENTS'))->getValue()) as $table) {
        foreach (['en', 'dv', 'ar'] as $locale) {
            expect(array_key_exists("courses_deleted_hold_{$table}", teachBook($locale, 'admin')))->toBeTrue("admin.courses_deleted_hold_{$table} is missing in {$locale}");
        }
    }

    app()->setLocale('en');
    expect(__('teach.flash_subject_saved'))->toBe('Subject saved.')
        ->and(__('admin.courses_deleted_title'))->toBe('Deleted courses');
});

it('names every Qur’an code the teacher’s and the learner’s screens show, in all three languages', function () {
    // The `quran` book (slice CT5a): one name per code, for the teacher's
    // screens and the learner's alike. A memorized range, a revision and how
    // often it comes round are the learner's page's own (slice CT5b).
    $needed = [
        'all',
        ...array_map(fn ($case) => 'assignment_type_'.$case->value, QuranAssignmentType::cases()),
        ...array_map(fn ($case) => 'status_'.$case->value, [...QuranAssignmentStatus::cases(), ...RecitationSubmissionStatus::cases(), ...HifzMilestoneStatus::cases()]),
        ...array_map(fn ($case) => 'milestone_type_'.$case->value, HifzMilestoneType::cases()),
        ...array_map(fn ($case) => 'mistake_'.$case->value, QuranMistakeType::cases()),
        ...array_map(fn ($case) => 'severity_'.$case->value, QuranMistakeSeverity::cases()),
        ...array_map(fn ($case) => 'result_'.$case->value, [...QuranLaneResult::cases(), ...QuranRevisionResult::cases()]),
        ...array_map(fn ($case) => 'overall_'.$case->value, QuranSessionOverallStatus::cases()),
        ...array_map(fn ($status) => 'attendance_'.$status, ['present', 'late', 'absent', 'excused']),
        ...array_map(fn ($case) => 'progress_'.$case->value, MemorizationStatus::cases()),
        ...array_map(fn ($case) => 'revision_'.$case->value, RevisionScheduleStatus::cases()),
        ...array_map(fn ($frequency) => 'frequency_'.$frequency, ['daily', 'weekly', 'monthly']),
    ];
    [$en, $dv, $ar] = [teachBook('en', 'quran'), teachBook('dv', 'quran'), teachBook('ar', 'quran')];

    foreach (array_unique($needed) as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("quran.{$key} is missing in English")
            ->and($dv[$key] ?? $en[$key] ?? null)->not->toBe($en[$key] ?? null, "quran.{$key} is missing or English in Dhivehi")
            ->and($ar[$key] ?? $en[$key] ?? null)->not->toBe($en[$key] ?? null, "quran.{$key} is missing or English in Arabic");
    }
});

it('serves the Teach Qur’an screens in Dhivehi, with the screen’s book and the Qur’an book', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    [$dv, $quran] = [teachBook('dv'), teachBook('dv', 'quran')];

    app()->setLocale('dv');
    foreach ([
        'teach.assignments' => ['Courses/Teach/QuranAssignments', 'qassign_title'],
        'teach.milestones' => ['Courses/Teach/QuranMilestones', 'qmile_title'],
        'teach.recitations' => ['Courses/Teach/RecitationQueue', 'qrec_title'],
    ] as $route => [$component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($admin)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)
                ->where("t.{$key}", $dv[$key])
                ->where('q.status_passed', $quran['status_passed']));
    }

    app()->setLocale('en');
    expect(__('teach.flash_qrec_reviewed'))->toBe('Recitation reviewed.')
        ->and(__('quran.mistake_wrong_haraka'))->toBe('wrong haraka');
});

it('serves Qur’an oversight, the reference, the mushafs, page mapping and the learner’s Qur’an page in Dhivehi, and says what was saved in Dhivehi', function () {
    // The mushaf screens are the Hifz dean's (`QuranMushafPolicy`).
    $dean = actingPeopleAdmin(['courses.manage', 'view_hifz_programs', 'manage_quran_mushaf']);
    $dean->assignRole(Role::findOrCreate('headmaster', 'web'));
    [$dv, $learn, $quran] = [teachBook('dv'), teachBook('dv', 'learn'), teachBook('dv', 'quran')];

    app()->setLocale('dv');
    foreach ([
        'catalog.quran.oversight' => ['Courses/Catalog/QuranOversight', 'qover_title'],
        'catalog.quran.index' => ['Courses/Catalog/QuranReference', 'qref_title'],
        'quran.mushafs.index' => ['Courses/Quran/Mushafs/Index', 'mushaf_title'],
        'quran.mushafs.create' => ['Courses/Quran/Mushafs/Create', 'mushaf_create'],
    ] as $route => [$component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($dean)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->post(route('quran.mushafs.store'), ['name' => 'CT5b mushaf', 'page_count' => 2])
        ->assertSessionHas('success', $dv['flash_mushaf_created']);
    $mushaf = QuranMushaf::query()->where('name', 'CT5b mushaf')->firstOrFail();
    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('quran.mushafs.show', $mushaf))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Quran/Mushafs/Show')
            ->where('t.mushaf_import_title', $dv['mushaf_import_title'])
            ->where('mushaf.pages_count', 2));
    // The last page says so, rather than offer a next page that is not there.
    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('quran.pages.show', ['mushaf' => $mushaf->id, 'pageNumber' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Quran/Pages/Show')
            ->where('t.qpage_back', $dv['qpage_back'])
            ->where('last_page', 2));

    // The learner's page: the shell's `learn` book, the Qur'an book for its
    // codes, and each surah's Arabic name.
    Surah::query()->create([
        'index' => 1, 'arabic_name' => 'الفاتحة', 'english_name' => 'Al-Fatihah',
        'transliteration' => 'Al-Fatihah', 'ayah_count' => 7, 'revelation_place' => 'Meccan',
        'juz_start' => 1, 'juz_end' => 1, 'is_active' => true,
    ]);
    $pupil = User::factory()->create();
    makeStudent(['user_id' => $pupil->id]);
    $this->withoutLocalizationMiddleware()->actingAs($pupil)
        ->get(route('learn.quran'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Learn/Quran')
            ->where('i18n.learn.quran_dashboard', $learn['quran_dashboard'])
            ->where('i18n.learn.pronounce_record', $learn['pronounce_record'])
            ->where('q.progress_needs_revision', $quran['progress_needs_revision'])
            ->where('surahs.0.arabic_name', 'الفاتحة'));

    app()->setLocale('en');
    expect(__('teach.flash_mushaf_created'))->toBe('Mushaf created.')
        ->and(__('teach.flash_qpage_position_saved'))->toBe('Word position saved.')
        ->and(__('learn.flash_recitation_submitted'))->toBe('Recitation submitted — your teacher will listen to it.');
});
