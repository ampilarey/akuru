<?php

use App\Domains\Courses\Actions\CheckCertificateEligibilityAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SuspendEnrollmentAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * What a course author, a marker and the certificate office are told when a
 * step is refused — and what a learner reads under *Still needed for this
 * certificate* — in the page's language (BACKLOG C19, slice CT6b-2b).
 *
 * The refusals were English inside the actions: *Progress is below the
 * minimum.*, *Cannot move from draft to archived.*, *This module still has 1
 * lessons. Move or delete those first (SPEC §12).* Most of the buttons that
 * met them post with `router` rather than a form, and nothing showed what
 * came back: Submit review, the review decision, the outline's reorder,
 * unlock rule, completion rule and block order, a certificate's Revoke. They
 * go through the phrase books now, and each page says them beside the row
 * whose button was pressed.
 */

/** @return list<string> the files whose refusals reach an author, a marker, the office or a learner's certificate */
function authoringRefusalSources(): array
{
    return [
        'app/Domains/Courses/Actions/CheckCertificateEligibilityAction.php',
        'app/Domains/Courses/Actions/IssueCertificateAction.php',
        'app/Domains/Courses/Actions/RevokeIssuedCertificateAction.php',
        'app/Domains/Courses/Actions/SaveCertificateTemplateAction.php',
        'app/Domains/Courses/Actions/SaveEngineCourseAction.php',
        'app/Domains/Courses/Actions/TransitionCourseWorkflowAction.php',
        'app/Domains/Courses/Actions/RecordCourseReviewDecisionAction.php',
        'app/Domains/Courses/Actions/DeleteCourseModuleAction.php',
        'app/Domains/Courses/Actions/PublishCourseModuleAction.php',
        'app/Domains/Courses/Actions/ReorderCourseModulesAction.php',
        'app/Domains/Courses/Actions/ReorderContentBlocksAction.php',
        'app/Domains/Courses/Actions/ReorderAssessmentQuestionsAction.php',
        'app/Domains/Courses/Actions/SaveLessonAction.php',
        'app/Domains/Courses/Actions/AttachLessonGlossaryItemAction.php',
        'app/Domains/Courses/Actions/StoreMediaContentBlockAction.php',
        'app/Domains/Courses/Actions/ValidateContentBlockDataAction.php',
        'app/Domains/Courses/Actions/NormalizeVideoEmbedUrlAction.php',
        'app/Domains/Courses/Actions/SaveQuestionAction.php',
        'app/Domains/Courses/Actions/ValidateNormalizationSettingsAction.php',
        'app/Domains/Courses/Actions/ResolveQuestionMediaAction.php',
        'app/Domains/Courses/Actions/SaveAssessmentAction.php',
        'app/Domains/Courses/Actions/AttachAssessmentQuestionAction.php',
        'app/Domains/Courses/Actions/SaveActivityAction.php',
        'app/Domains/Courses/Actions/SaveCourseSubjectAction.php',
        'app/Domains/Courses/Actions/StoreGlossaryMediaAction.php',
        'app/Domains/Courses/Actions/ModerateForumAction.php',
        'app/Domains/Courses/Actions/SuspendEnrollmentAction.php',
        'app/Domains/Courses/Actions/RestoreCourseAction.php',
        'app/Domains/Courses/Components/Arabic/Actions/SaveArabicLetterAction.php',
        'app/Domains/Courses/Components/Arabic/Actions/SaveArabicHarakahAction.php',
        'app/Domains/Courses/Components/Clubs/Actions/AddClubMemberAction.php',
        'app/Domains/Progress/Actions/ReviewAttemptAction.php',
    ];
}

/** @return list<string> every phrase-book key the files name, the ones built from a part included */
function authoringRefusalKeys(): array
{
    $keys = refusalKeysIn(authoringRefusalSources());

    // Keys a file builds from a code: what a module still holds, a glossary
    // slot's kind, a course's workflow status, a block's type, an
    // enrolment's status.
    $built = [
        'teach.module_holds_lessons', 'teach.module_holds_content_blocks', 'teach.module_holds_student_lesson_progress',
        'teach.error_glossary_slot_audio', 'teach.error_glossary_slot_image',
        'teach.workflow_draft', 'teach.workflow_in_review', 'teach.workflow_published', 'teach.workflow_archived',
        // (A PDF block is "PDF" in all three, so it is not asked for a script.)
        'teach.block_image', 'teach.block_audio', 'teach.block_video', 'teach.block_download',
        'admin.enrolments_status_pending', 'admin.enrolments_status_approved', 'admin.enrolments_status_active',
        'admin.enrolments_status_rejected', 'admin.enrolments_status_suspended', 'admin.enrolments_status_completed',
        'admin.enrolments_status_cancelled',
    ];

    return array_values(array_unique(array_merge($keys, $built)));
}

function authoringCourse(): Course
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);

    return app(SaveEngineCourseAction::class)->execute([
        'title' => 'Authoring '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
}

function certificateLearner(Course $course, int $progress): array
{
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Still', 'last_name' => 'Working']);
    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'unified_student_id' => $student->id,
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => $progress,
    ]);

    return [$user, $student, $enrollment];
}

it('leaves no authoring refusal in English inside the code', function () {
    // `refusalEnglishIn` (tests/Support) reads a file two ways: a literal
    // that reads as a sentence, and a literal with words inside a
    // `withMessages(...)` call, where a refusal built of pieces hides.
    $left = array_merge(...array_map('refusalEnglishIn', authoringRefusalSources()));

    expect($left)->toBe([]);
});

it('has every refusal those files say, in Dhivehi and in Arabic', function () {
    foreach (authoringRefusalKeys() as $key) {
        $english = trans($key, [], 'en');
        expect($english)->not->toBe($key, "{$key} has no English");

        // A joiner (", ") has no letters to be in any script.
        if (! preg_match('/\p{L}/u', $english)) {
            expect(trans($key, [], 'dv'))->not->toBe($key)->and(trans($key, [], 'ar'))->not->toBe($key);

            continue;
        }
        expect(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('tells a learner on a Dhivehi course page what the certificate still needs, in Dhivehi', function () {
    $course = authoringCourse();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    CertificateTemplate::query()->create([
        'name' => 'Completion', 'kind' => 'course_completion', 'course_id' => $course->id,
        'rules' => ['min_progress_percent' => 80], 'body_html' => '<p>Well done</p>', 'active' => true,
    ]);
    [$learner] = certificateLearner($course, 40);

    app()->setLocale('dv');
    $reasons = $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->get(route('learn.courses.show', $course->id))
        ->assertOk()
        ->viewData('page')['props']['certificate']['reasons'];

    expect($reasons)->toContain(trans('learn.cert_reason_progress', [], 'dv'));
    foreach ($reasons as $reason) {
        expect($reason)->not->toMatch('/[A-Za-z]{2,}/', "English on the Dhivehi page: {$reason}");
    }
});

it('says a reason once, even when two rules arrive at it', function () {
    $course = authoringCourse();
    $template = CertificateTemplate::query()->create([
        'name' => 'Final', 'kind' => 'course_completion', 'course_id' => $course->id,
        'rules' => ['require_final_assessment' => true, 'min_score' => 50], 'body_html' => '<p>x</p>', 'active' => true,
    ]);
    [, $student] = certificateLearner($course, 100);
    $assessment = Assessment::query()->create(['course_id' => $course->id, 'title' => 'Essay paper', 'status' => 'published']);
    AssessmentAttempt::query()->create([
        'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 1,
        'status' => 'submitted', 'score' => 90, 'max_score' => 100,
    ]);

    // Handed in and not yet marked: the final-assessment rule and the
    // minimum-score rule each reach "awaiting teacher marking", and the
    // course page listed the line twice.
    $reasons = app(CheckCertificateEligibilityAction::class)->execute($template, (int) $student->id, (int) $course->id)['reasons'];

    expect($reasons)->toContain(trans('learn.cert_reason_awaiting_marking'))
        ->and(array_count_values($reasons))->each->toBe(1);
});

it('refuses a certificate the learner has not earned on the Arabic issue form, the reasons in Arabic', function () {
    $course = authoringCourse();
    $template = CertificateTemplate::query()->create([
        'name' => 'Approval', 'kind' => 'course_completion', 'course_id' => null,
        'rules' => ['require_teacher_approval' => true], 'body_html' => '<p>x</p>', 'active' => true,
    ]);
    [, $student] = certificateLearner($course, 100);

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url('/ar/catalog/certificates'))
        ->post('/catalog/certificates/issue', [
            'certificate_template_id' => $template->id,
            'student_id' => $student->id,
            'academic_year_id' => makeYear()->id,
        ])
        ->assertSessionHasErrors(['student_id' => trans('learn.cert_reason_teacher_approval', [], 'ar')]);
});

it('refuses a move the workflow does not allow in Dhivehi, the statuses named in Dhivehi', function () {
    $course = authoringCourse();

    $this->actingAs(actingPeopleAdmin(['courses.manage', 'courses.publish']))
        ->withHeader('Referer', url('/dv/catalog/courses'))
        ->post("/catalog/courses/{$course->id}/transition", ['workflow_status' => 'archived'])
        ->assertSessionHasErrors(['workflow_status' => trans('teach.error_workflow_move', [
            'from' => trans('teach.workflow_draft', [], 'dv'),
            'to' => trans('teach.workflow_archived', [], 'dv'),
        ], 'dv')]);
});

it('asks for the reason a course is sent back, in Dhivehi', function () {
    $course = authoringCourse();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);

    $this->actingAs(actingPeopleAdmin(['courses.manage', 'courses.publish']))
        ->withHeader('Referer', url('/dv/catalog/courses'))
        ->post("/catalog/courses/{$course->id}/review-decision", ['decision' => 'changes_requested', 'comment' => ''])
        ->assertSessionHasErrors(['comment' => trans('teach.error_review_say_why', [], 'dv')]);

    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::InReview);
});

it('names what keeps a module in Arabic when its deletion is refused', function () {
    $course = authoringCourse();
    $module = app(SaveCourseModuleAction::class)->execute(['course_id' => $course->id, 'title' => 'Unit 1']);
    Lesson::query()->create([
        'course_id' => $course->id, 'course_module_id' => $module->id,
        'title' => 'A lesson', 'slug' => 'a-lesson-'.uniqueFixtureSuffix(), 'position' => 1,
    ]);

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url("/ar/catalog/courses/{$course->id}/outline"))
        ->delete("/catalog/courses/{$course->id}/modules/{$module->id}")
        ->assertSessionHasErrors(['module' => trans('teach.error_module_holds', [
            'things' => trans_choice('teach.module_holds_lessons', 1, ['count' => 1], 'ar'),
        ], 'ar')]);

    expect(trans('teach.error_module_holds', ['things' => 'دروس (1)'], 'ar'))->not->toContain('SPEC');
});

it('tells the office in Dhivehi why an enrolment cannot be suspended, its status named in Dhivehi', function () {
    $course = authoringCourse();
    [, , $enrollment] = certificateLearner($course, 0);
    $enrollment->forceFill(['status' => 'rejected'])->save();

    app()->setLocale('dv');
    try {
        app(SuspendEnrollmentAction::class)->execute($enrollment->fresh());
        $said = null;
    } catch (ValidationException $e) {
        $said = $e->errors()['status'][0];
    }

    expect($said)->toBe(trans('admin.error_suspend_not_live', ['status' => trans('admin.enrolments_status_rejected', [], 'dv')], 'dv'));
});

it('offers a module\'s Publish only to an author who may publish', function () {
    $course = authoringCourse();

    // Publishing a module answers to `courses.publish`; the outline offered
    // the button to every author, and one without it pressed it into a bare
    // "Forbidden" (the slice's walk).
    $without = $this->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin(['courses.manage']))
        ->get(route('catalog.courses.outline', $course->id))->assertOk();
    $with = $this->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin(['courses.manage', 'courses.publish']))
        ->get(route('catalog.courses.outline', $course->id))->assertOk();

    expect($without->viewData('page')['props']['canPublish'])->toBeFalse()
        ->and($with->viewData('page')['props']['canPublish'])->toBeTrue()
        ->and(file_get_contents(resource_path('js/Pages/Courses/Catalog/Outline.jsx')))->toContain('{canPublish && (');
});

it('has every page whose buttons post with router say their refusals beside the row', function () {
    $pages = [
        'resources/js/Pages/Courses/Catalog/Index.jsx',
        'resources/js/Pages/Courses/Catalog/Outline.jsx',
        'resources/js/Pages/Courses/Catalog/Certificates.jsx',
        'resources/js/Pages/Courses/Catalog/Assessments.jsx',
        'resources/js/Pages/Courses/DeletedCourses.jsx',
        'resources/js/Pages/Courses/Forum/Topic.jsx',
    ];

    foreach ($pages as $path) {
        $source = file_get_contents(base_path($path));
        expect(str_contains($source, 'useRowRefusals('))->toBeTrue("{$path} keeps no refusals")
            ->and(str_contains($source, 'errorsFor('))->toBeTrue("{$path} says none");

        // A `router` visit that is not marked with the row it came from is a
        // button whose refusal nobody shows.
        expect(routerVisitsWithoutRow($path))->toBe([]);
    }
});
