<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §20 "Question Bank", part two: the form and the list.
 *
 * The attachment slice fixed what a question could carry. This is about what an
 * author can reach and what the bank can be narrowed to, and it closes the
 * three §20 fields the previous slice explicitly recorded and left.
 *
 * **Rule 5.** `CatalogQuestionController::payload()` built its array entirely
 * out of `$request->input()` with **no `validate()` call anywhere on the save
 * path** — the same defect §19's assessment form had, in the same shape.
 * `SaveQuestionAction` refuses an unknown type and an empty text; everything
 * else was trusted. `difficulty` was *cast*: any unrecognised value became
 * `medium` silently. `subject_id` was cast to int and stored whether or not the
 * subject existed — there is no foreign key on that column — so an author
 * filing a question under a deleted subject got a save confirmation and a row
 * pointing nowhere. Casting is not validating, and an input silently turned
 * into something valid is the failure that leaves an author certain they set a
 * thing they did not.
 *
 * **`explanation` was unreachable at both ends.** §20 lists it, §21 snapshots
 * it, and the server puts it back into the student's payload exactly when §19's
 * "Show/hide correct answers" is on and the attempt is scored. There was no
 * control to write one, and no branch to draw one — so a student was shown the
 * right answer and withheld the reason, which is the half that teaches.
 *
 * **`course_id` and the filters were the same gap from opposite sides.** §20
 * lists "Course ID nullable"; `index` has accepted `subject_id`, `course_id`
 * and `question_type` filters since the bank was built; and no control on the
 * page could set any of them, nor was a course list ever sent. A bank you
 * cannot narrow stops being usable at a few hundred questions.
 *
 * **`category_id` is deliberately left alone.** §20 names it, and nothing in
 * the system defines what a question category *is*: no table, no foreign key,
 * and the identical unanchored column on `glossary_items`. Giving it a control
 * would mean choosing a meaning for it, which is a decision rather than a
 * cleanup.
 */
uses(RefreshDatabase::class);

function bankFormAdmin(): object
{
    return actingPeopleAdmin(['courses.manage']);
}

function bankFormCourse(): object
{
    return app(SaveEngineCourseAction::class)->execute([
        'title' => 'Bank form '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => bankFormAdmin()->id,
    ]);
}

it('validates the question form instead of trusting it (rule 5)', function () {
    $admin = bankFormAdmin();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'interpretive_dance',
            'question_text' => '',
            'difficulty' => 'impossible',
            'subject_id' => 999999,
            'course_id' => 999999,
        ])
        ->assertSessionHasErrors(['question_type', 'question_text', 'difficulty', 'subject_id', 'course_id']);

    expect(Question::query()->count())->toBe(0);
});

it('refuses a difficulty it used to silently rewrite to medium', function () {
    // The old line was `in_array($difficulty, ['easy','medium','hard']) ? … : 'medium'`
    // with nothing upstream objecting, so a typo was stored as `medium` and the
    // author was told the question saved.
    $admin = bankFormAdmin();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'mcq_single',
            'question_text' => 'Which one?',
            'difficulty' => 'hardish',
        ])
        ->assertSessionHasErrors('difficulty');

    expect(Question::query()->count())->toBe(0);
});

it('stores the course a question belongs to, and accepts none', function () {
    $admin = bankFormAdmin();
    $course = bankFormCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'mcq_single',
            'question_text' => 'Course-bound question',
            'course_id' => $course->id,
        ])
        ->assertRedirect();

    expect((int) Question::query()->latest('id')->value('course_id'))->toBe((int) $course->id);

    // A bank is reusable across courses, so "no course" is the norm and not an
    // omission — §20 calls the field nullable for that reason.
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'mcq_single',
            'question_text' => 'Reusable question',
        ])
        ->assertRedirect();

    expect(Question::query()->latest('id')->value('course_id'))->toBeNull();
});

it('stores an explanation the author can now type', function () {
    $admin = bankFormAdmin();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'mcq_single',
            'question_text' => 'Why is this right?',
            'explanation' => 'Because the verb agrees with its subject.',
        ])
        ->assertRedirect();

    expect(Question::query()->latest('id')->value('explanation'))
        ->toBe('Because the verb agrees with its subject.');
});

it('draws the explanation in the player, not just the correct answer', function () {
    // The server already puts `explanation` back into the student's snapshot
    // when §19's "Show/hide correct answers" is on and the attempt is scored —
    // `StartAssessmentAttemptAction::serialize()` only strips it without
    // `includeKeys`. Nothing rendered it, so the reveal showed the answer and
    // withheld the reason.
    $source = (string) file_get_contents(base_path('resources/js/Pages/Courses/Learn/Assessment.jsx'));

    expect($source)->toContain('snapshot.explanation');
});

it('serves the course list and the active filters to the bank', function () {
    $admin = bankFormAdmin();
    $course = bankFormCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/questions?course_id='.$course->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Questions')
            ->has('courses')
            // Without this the filter controls cannot show what is in force,
            // and a filtered page looks identical to an unfiltered one.
            ->where('filters.course_id', (string) $course->id));
});

it('narrows the bank by the filters index has always accepted', function () {
    $admin = bankFormAdmin();
    $course = bankFormCourse();

    $this->actingAs($admin)->withoutLocalizationMiddleware()->post('/catalog/questions', [
        'question_type' => 'mcq_single', 'question_text' => 'In the course', 'course_id' => $course->id,
    ]);
    $this->actingAs($admin)->withoutLocalizationMiddleware()->post('/catalog/questions', [
        'question_type' => 'true_false', 'question_text' => 'Loose in the bank',
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/questions?course_id='.$course->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.question_text', 'In the course'));

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/questions?question_type=true_false')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.question_text', 'Loose in the bank'));
});
