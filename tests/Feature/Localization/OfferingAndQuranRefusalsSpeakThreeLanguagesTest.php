<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Quran\Actions\RecommendQuranMilestoneAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\EnforceSeatLimitAction;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Actions\TransitionOfferingStatusAction;
use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * What the office is told when an offering, a session, attendance or a halaqa
 * link refuses a step, and what a teacher is told by the Qur'an component's
 * reviews, assignments, milestones and session sheet — in the page's language
 * (BACKLOG C19, slice CT6b-2c).
 *
 * They were English inside the actions (*Offering slug must be unique within
 * the course.*, *An offering cannot go from Completed to Draft.*), and the
 * pages dropped most of them: the offerings form showed its status and
 * certificate-rule refusals only, the recitation review its audio's only, and
 * Pin now, Sync dual-write, a session's halaqa link, a milestone's Review,
 * Approve and Reject and an assignment's Cancel showed none.
 */

/** @return list<string> */
function offeringAndQuranRefusalSources(): array
{
    return [
        'app/Domains/Offerings/Actions/SaveCourseOfferingAction.php',
        'app/Domains/Offerings/Actions/RecordOfferingAttendanceAction.php',
        'app/Domains/Offerings/Actions/SaveOfferingSessionAction.php',
        'app/Domains/Offerings/Actions/SaveOfferingHalaqaLinkAction.php',
        'app/Domains/Offerings/Actions/SaveOfferingHalaqaSessionLinkAction.php',
        'app/Domains/Offerings/Actions/SyncHalaqaDualWriteAction.php',
        'app/Domains/Offerings/Actions/TransitionOfferingStatusAction.php',
        'app/Domains/Offerings/Actions/MapHalaqaProgramAction.php',
        'app/Domains/Offerings/Actions/EnforceSeatLimitAction.php',
        'app/Domains/Courses/Components/Quran/Actions/ReviewRecitationAction.php',
        'app/Domains/Courses/Components/Quran/Actions/SaveMemorizationProgressAction.php',
        'app/Domains/Courses/Components/Quran/Actions/SaveQuranAssignmentAction.php',
        'app/Domains/Courses/Components/Quran/Actions/RecommendQuranMilestoneAction.php',
        'app/Domains/Courses/Components/Quran/Actions/SaveQuranSessionRecordAction.php',
        'app/Domains/Courses/Components/Quran/Actions/SaveRevisionScheduleAction.php',
    ];
}

function refusalsOffering(array $overrides = []): CourseOffering
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Offerings '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    return app(SaveCourseOfferingAction::class)->execute($overrides + [
        'course_id' => $course->id,
        'title' => 'Intake A',
        'delivery_mode' => 'face_to_face',
        'status' => 'draft',
    ]);
}

it('leaves no offering or Qur\'an refusal in English inside the code', function () {
    // The translation import is the console's alone, and stays English like
    // the other commands.
    $left = array_merge(...array_map('refusalEnglishIn', offeringAndQuranRefusalSources()));

    expect($left)->toBe([]);
});

it('has every refusal those files say, in Dhivehi and in Arabic', function () {
    $keys = array_merge(refusalKeysIn(offeringAndQuranRefusalSources()), array_map(
        fn (OfferingStatus $status): string => 'teach.offering_status_'.$status->value,
        OfferingStatus::cases(),
    ));

    expect($keys)->not->toBeEmpty();
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('refuses a second intake of the same name in Dhivehi, from a Dhivehi page', function () {
    $first = refusalsOffering();

    // The slug is made from the title, so a second "Intake A" on the same
    // course collides. The form showed nothing: `slug` is not a field on it.
    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url('/dv/catalog/offerings'))
        ->post('/catalog/offerings', ['course_id' => $first->course_id, 'title' => 'Intake A', 'delivery_mode' => 'face_to_face'])
        ->assertSessionHasErrors(['slug' => trans('teach.error_offering_slug', [], 'dv')]);

    expect(CourseOffering::query()->where('course_id', $first->course_id)->count())->toBe(1);
});

it('names both statuses in Arabic when an offering may not move between them', function () {
    $offering = refusalsOffering(['status' => 'completed']);

    app()->setLocale('ar');
    try {
        app(TransitionOfferingStatusAction::class)->execute($offering, OfferingStatus::Draft);
        $said = null;
    } catch (ValidationException $e) {
        $said = $e->errors()['status'][0];
    }

    expect($said)->toBe(trans('teach.error_offering_move', [
        'from' => trans('teach.offering_status_completed', [], 'ar'),
        'to' => trans('teach.offering_status_draft', [], 'ar'),
    ], 'ar'));
});

it('says in Dhivehi that dual-write is off when the office asks it to sync', function () {
    config(['quran.halaqa_dual_write' => false]);
    $offering = refusalsOffering();

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withHeader('Referer', url("/dv/catalog/offerings/{$offering->id}/sessions"))
        ->post("/catalog/offerings/{$offering->id}/halaqa/sync")
        ->assertSessionHasErrors(['dual_write' => trans('teach.error_dual_write_off', [], 'dv')]);
});

it('says a full intake in the page\'s language when the caller names no "full" of its own', function () {
    $offering = refusalsOffering(['seat_limit' => 1]);
    $student = makeStudent(['first_name' => 'Seat', 'last_name' => 'Holder']);
    CourseEnrollment::query()->create([
        'course_id' => $offering->course_id, 'course_offering_id' => $offering->id,
        'unified_student_id' => $student->id, 'status' => 'active',
        'payment_status' => 'not_required', 'enrollment_type' => 'self_learning',
    ]);

    app()->setLocale('dv');
    try {
        app(EnforceSeatLimitAction::class)->execute('course_offerings', (int) $offering->id, 'seat_limit', 'course_enrollments', 'course_offering_id', ['active']);
        $said = null;
    } catch (ValidationException $e) {
        $said = $e->errors()['course_offering_id'][0];
    }

    expect($said)->toBe(trans('common.error_no_seats', [], 'dv'));
});

it('refuses a milestone of no known type in Dhivehi', function () {
    app()->setLocale('dv');

    expect(fn () => app(RecommendQuranMilestoneAction::class)->execute(['type' => 'half_way']))
        ->toThrow(ValidationException::class, trans('quran.error_milestone_type', [], 'dv'));
});

it('has every offering and Qur\'an page whose buttons post with router say their refusals beside the row', function () {
    $pages = [
        'resources/js/Pages/Offerings/Catalog/Index.jsx',
        'resources/js/Pages/Offerings/Catalog/Sessions.jsx',
        'resources/js/Pages/Courses/Teach/QuranMilestones.jsx',
        'resources/js/Pages/Courses/Teach/QuranAssignments.jsx',
    ];

    foreach ($pages as $path) {
        $source = (string) file_get_contents(base_path($path));
        expect(str_contains($source, 'useRowRefusals('))->toBeTrue("{$path} keeps no refusals")
            ->and(str_contains($source, 'errorsFor('))->toBeTrue("{$path} says none")
            ->and(routerVisitsWithoutRow($path))->toBe([]);
    }

    // The forms that dropped all but a field or two now list the rest.
    expect((string) file_get_contents(resource_path('js/Pages/Offerings/Catalog/Index.jsx')))->toContain("<FormErrors errors={form.errors} except={['status'")
        ->and((string) file_get_contents(resource_path('js/Pages/Courses/Teach/RecitationQueue.jsx')))->toContain("<FormErrors errors={form.errors} except={['correction_audio']} />");
});
