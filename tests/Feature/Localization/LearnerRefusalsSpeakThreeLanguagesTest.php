<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\ResolveEnrollmentAccessWindowAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * What a learner is told when a step is refused, in the page's language
 * (BACKLOG C19, slice CT6b-2a, STATUS §5pb).
 *
 * The refusals were written in English inside the actions — *Retake limit
 * reached.*, *This offering has no remaining seats.*, *Discount code not found
 * or inactive.* — and the enrol and attempt ones were shown by no page: the
 * catalog and the course page read no errors at all, and the activity and the
 * assessment read only their own field's. They go through the phrase books
 * now, and the pages say them.
 */

/** @return list<string> the files whose refusals reach a learner */
function learnerRefusalSources(): array
{
    return [
        'app/Domains/Progress/Actions/AttachAttemptMediaAction.php',
        'app/Domains/Progress/Actions/RecordLessonProgressAction.php',
        'app/Domains/Progress/Actions/SaveActivityAttemptAction.php',
        'app/Domains/Progress/Actions/SaveAssessmentAttemptAction.php',
        'app/Domains/Progress/Actions/StartAssessmentAttemptAction.php',
        'app/Domains/Progress/Actions/SubmitAssessmentAttemptAction.php',
        'app/Domains/Courses/Actions/EnrollSelfLearningAction.php',
        'app/Domains/Courses/Actions/StartOrCompleteLessonProgressAction.php',
        'app/Domains/Courses/Actions/ResolveActivityDefinitionAction.php',
        'app/Domains/Courses/Actions/ScoreActivityAnswersAction.php',
        'app/Domains/Courses/Actions/ListUnansweredRequiredQuestionsAction.php',
        'app/Domains/Courses/Actions/AuthorizeAssessmentAccessAction.php',
        'app/Domains/Courses/Actions/AuthorizeActivityAccessAction.php',
        'app/Domains/Courses/Actions/AuthorizeLessonAccessAction.php',
        'app/Domains/Courses/Actions/ResolveEnrollmentAccessWindowAction.php',
        'app/Domains/Courses/Actions/ServeStudentCertificateAction.php',
        'app/Domains/Courses/Http/Controllers/LessonPlayerController.php',
        'app/Domains/Courses/Http/Controllers/LearnLessonController.php',
        'app/Domains/Courses/Http/Controllers/LearnCatalogController.php',
        'app/Domains/Courses/Http/Controllers/LearnActivityController.php',
        'app/Domains/Courses/Http/Controllers/LearnAssessmentController.php',
        'app/Domains/Courses/Components/Quran/Actions/SubmitRecitationAction.php',
        'app/Domains/Offerings/Actions/ReserveOfferingSeatAction.php',
        'app/Domains/Commerce/Actions/ResolveDiscountAction.php',
        'app/Http/Middleware/ConvertEnroll403ToRedirect.php',
    ];
}

/** @return list<string> a file's string literals that read as an English sentence, comments aside */
function englishSentencesIn(string $file): array
{
    $found = [];
    foreach (token_get_all(file_get_contents(base_path($file))) as $token) {
        if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $text = substr($token[1], 1, -1);
        if (preg_match('/^[A-Z][a-z\']*( \S+)+[.!?]$/', $text)) {
            $found[] = "{$file}:{$token[2]} {$text}";
        }
    }

    return $found;
}

function refusalCourse(int $fee = 0): Course
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Refusals '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->registration_fee_amount = $fee;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

function refusalLearner(): User
{
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Refused', 'last_name' => 'Learner']);

    return $user;
}

it('says no learner refusal in English inside the code', function () {
    $left = array_merge(...array_map('englishSentencesIn', learnerRefusalSources()));

    expect($left)->toBe([]);
});

it('has every refusal those files say, in Dhivehi and in Arabic', function () {
    $keys = [];
    foreach (learnerRefusalSources() as $file) {
        preg_match_all("/__\\('([a-z]+\\.[a-z_]+)'/", file_get_contents(base_path($file)), $found);
        $keys = array_merge($keys, $found[1]);
    }
    $keys = array_values(array_unique($keys));

    expect($keys)->not->toBeEmpty();
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('refuses a retake past the limit in Dhivehi, from a Dhivehi page', function () {
    $course = refusalCourse();
    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Pick the sun letter',
        'pattern' => 'selection',
        'max_score' => 1,
        'data' => [
            'prompt' => 'Which is a sun letter?',
            'options' => [['id' => 'a', 'label' => 'Right'], ['id' => 'b', 'label' => 'Wrong']],
            'correct_ids' => ['a'],
        ],
        'settings' => ['retake_limit' => 1],
    ]);
    $learner = refusalLearner();
    app(EnrollSelfLearningAction::class)->execute($learner->id, (int) $course->id, null);

    $refused = null;
    foreach (range(1, 4) as $_) {
        $response = $this->actingAs($learner)
            ->withHeader('Referer', url("/dv/learn/activities/{$activity->id}"))
            ->post("/learn/activities/{$activity->id}/submit", ['answers' => ['selected_ids' => ['b']]]);
        $refused = session('errors')?->first('attempt');
        if ($refused) {
            break;
        }
    }

    expect($refused)->toBeIn([trans('learn.error_retake_limit', [], 'dv'), trans('learn.error_retakes_not_allowed', [], 'dv')]);
});

it('refuses an unknown discount code in Dhivehi at the course checkout', function () {
    $course = refusalCourse(fee: 100);

    $this->actingAs(refusalLearner())
        ->withHeader('Referer', url('/dv/learn/catalog'))
        ->post("/learn/courses/{$course->id}/enroll", ['discount_code' => 'NO-SUCH-CODE'])
        ->assertSessionHasErrors(['discount_code' => trans('common.error_discount_not_found', [], 'dv')]);

    // The code is checked before anything is made: no enrolment waits on it.
    expect(CourseEnrollment::query()->where('course_id', $course->id)->exists())->toBeFalse();
});

it('refuses a full intake in Arabic, from an Arabic page', function () {
    $course = refusalCourse();
    $intake = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'One seat',
        'delivery_mode' => 'live_online',
        'status' => 'open',
        'seat_limit' => 1,
    ]);
    $this->actingAs(refusalLearner())
        ->withHeader('Referer', url('/ar/learn/catalog'))
        ->post("/learn/courses/{$course->id}/enroll", ['offering_id' => $intake->id])
        ->assertSessionHasNoErrors();

    $this->actingAs(refusalLearner())
        ->withHeader('Referer', url('/ar/learn/catalog'))
        ->post("/learn/courses/{$course->id}/enroll", ['offering_id' => $intake->id])
        ->assertSessionHasErrors(['course_offering_id' => trans('learn.error_offering_full', [], 'ar')]);
});

it('tells a learner they are enrolled in Dhivehi, and in English on an English page', function () {
    $course = refusalCourse();

    $this->actingAs(refusalLearner())
        ->withHeader('Referer', url('/dv/learn/catalog'))
        ->post("/learn/courses/{$course->id}/enroll")
        ->assertSessionHas('success', trans('learn.flash_enrolled', [], 'dv'));

    $this->actingAs(refusalLearner())
        ->withHeader('Referer', url('/en/learn/catalog'))
        ->post("/learn/courses/{$course->id}/enroll")
        ->assertSessionHas('success', 'Enrolled.');
});

it('says why an activity will not open, in Dhivehi', function () {
    $course = refusalCourse();
    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Not for strangers',
        'pattern' => 'selection',
        'max_score' => 1,
        'data' => [
            'prompt' => 'Which?',
            'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']],
            'correct_ids' => ['a'],
        ],
    ]);

    // A learner who never enrolled is refused, and the page says why. (A
    // test's routes carry no language prefix; the page's language is set
    // as the localization middleware would set it.)
    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs(refusalLearner())
        ->get(route('learn.activities.show', $activity->id))
        ->assertForbidden()
        ->assertSee(trans('learn.error_needs_enrolment', [], 'dv'));
});

it('says when a learner’s access opens in the page’s language, the date in figures', function () {
    $enrollment = (new CourseEnrollment)->forceFill(['access_starts_at' => now()->addDays(3)->setTime(9, 0)]);

    app()->setLocale('dv');
    $dhivehi = app(ResolveEnrollmentAccessWindowAction::class)->execute($enrollment)['message'];
    app()->setLocale('ar');
    $arabic = app(ResolveEnrollmentAccessWindowAction::class)->execute($enrollment)['message'];

    $date = now()->addDays(3)->format('Y-m-d').' 09:00';
    expect($dhivehi)->toBe(trans('learn.error_access_starts', ['date' => $date], 'dv'))
        ->and($arabic)->toBe(trans('learn.error_access_starts', ['date' => $date], 'ar'));
});
