<?php

use App\Domains\Courses\Actions\ListPublishedAssessmentsAction;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A soft-deleted row is invisible to Eloquent and perfectly visible to the
 * query builder.
 *
 * `ReserveOfferingSeatAction` already carried this lesson — *"a soft-deleted
 * enrolment was holding its seat forever"* — and it had reached exactly one
 * file. A sweep of every raw read against a soft-deleting table found three
 * more places it had not.
 */
it('stops offering an assessment that has been deleted', function () {
    // Feeds three pickers: the course outline, the offering list, and the
    // certificate-issue options. A deleted assessment stayed on offer in all
    // three, so an admin could attach one to an offering.
    $course = Course::factory()->create(['workflow_status' => 'published', 'status' => 'open']);

    $live = Assessment::query()->create([
        'course_id' => $course->id, 'title' => 'Live assessment',
        'status' => 'published', 'kind' => 'quiz',
    ]);
    $deleted = Assessment::query()->create([
        'course_id' => $course->id, 'title' => 'Deleted assessment',
        'status' => 'published', 'kind' => 'quiz',
    ]);
    $deleted->delete();

    $titles = app(ListPublishedAssessmentsAction::class)->execute()->pluck('title');

    expect($titles)->toContain('Live assessment')
        ->and($titles)->not->toContain('Deleted assessment');
});
