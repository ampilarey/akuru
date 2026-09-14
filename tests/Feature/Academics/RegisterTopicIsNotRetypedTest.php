<?php

use App\Domains\Academics\Actions\SubmitRegisterAction;
use App\Domains\Academics\Models\PlanTopic;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Picking a plan topic does not ask the teacher to type its title again.
 *
 * ## The defect (KNOWN_ISSUES #16)
 *
 * Two fields sat one under the other: a **Plan topic** select, and a textarea
 * labelled **What was taught**. Choosing "Sun and moon letters" answered the
 * second question as well as the first, but the empty box below still read as
 * a question, so it got the same words typed into it — twice in two rehearsal
 * rounds, by people who knew the form.
 *
 * The form then confirmed the habit. When the box was left empty the action
 * copied the topic's title into `taught_summary`, so re-opening the register
 * showed the teacher a summary they had never written, sitting in the box as
 * if that were where titles go.
 *
 * ## What changed
 *
 * The title stays on the topic (rule 11). The box asks only for what the title
 * leaves out, and an answer that merely repeats the title is dropped rather
 * than stored beside it.
 *
 * ## What this does not claim
 *
 * Dropping the echo does not stop a teacher writing "Sun and moon letters,
 * again" — nor should it. Only an exact repeat goes, and the tests below pin
 * both edges: the echo is dropped, anything with more in it survives whole.
 */
function registerWithTopic(string $title = 'Sun and moon letters'): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $class = makeClass($year);
    $subject = makeSubject();

    $plan = makeCoursePlan([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
    ]);

    $topic = PlanTopic::query()->create([
        'course_plan_id' => $plan->id,
        'order' => 1,
        'title' => $title,
        'is_completed' => false,
    ]);

    $log = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'period_id' => makePeriodRow()->id,
    ]);

    return [$log, $topic, $teacher];
}

it('keeps the topic title on the topic rather than copying it onto the log', function () {
    [$log, $topic, $teacher] = registerWithTopic();

    $submitted = app(SubmitRegisterAction::class)->execute(
        $log,
        ['plan_topic_id' => $topic->id],
        (int) $teacher->user_id,
    );

    expect($submitted->plan_topic_id)->toBe($topic->id)
        ->and($submitted->taught_summary)->toBeNull();
});

it('drops a summary that only repeats the title, however it was typed', function () {
    $echoes = [
        'Sun and moon letters',
        'sun and moon letters',
        '  Sun   and moon letters  ',
        'Sun and moon letters.',
    ];

    foreach ($echoes as $echo) {
        [$log, $topic, $teacher] = registerWithTopic();

        $submitted = app(SubmitRegisterAction::class)->execute(
            $log,
            ['plan_topic_id' => $topic->id, 'taught_summary' => $echo],
            (int) $teacher->user_id,
        );

        expect($submitted->taught_summary)->toBeNull(
            "'{$echo}' says nothing the topic title does not, so it should not be stored beside it."
        );
    }
});

it('keeps a summary that says more than the title', function () {
    [$log, $topic, $teacher] = registerWithTopic();

    $submitted = app(SubmitRegisterAction::class)->execute(
        $log,
        [
            'plan_topic_id' => $topic->id,
            'taught_summary' => 'Sun and moon letters — only the first half, class ran short',
        ],
        (int) $teacher->user_id,
    );

    expect($submitted->taught_summary)
        ->toBe('Sun and moon letters — only the first half, class ran short');
});

it('still keeps a free-text summary when no topic is picked', function () {
    [$log, , $teacher] = registerWithTopic();

    $submitted = app(SubmitRegisterAction::class)->execute(
        $log,
        ['taught_summary' => 'Revision, no plan topic for it'],
        (int) $teacher->user_id,
    );

    expect($submitted->plan_topic_id)->toBeNull()
        ->and($submitted->taught_summary)->toBe('Revision, no plan topic for it');
});

it('shows the teacher the title they picked instead of an empty question', function () {
    [$log, $topic, $teacher] = registerWithTopic();

    $user = User::query()->findOrFail($teacher->user_id);
    Permission::findOrCreate('registers.fill', 'web');
    $user->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.show', $log))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Show')
            // The select's options carry the titles, which is what the page
            // needs to tell the teacher what the register will say. Without
            // this the screen has nothing to show but the empty box.
            ->where('topics.0.title', $topic->title)
            ->where('topics.0.id', $topic->id)
        );
});
