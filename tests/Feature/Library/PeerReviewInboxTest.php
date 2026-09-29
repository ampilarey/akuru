<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\AssignResearchReviewerAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\DeclareReviewerNoConflictAction;
use App\Domains\Library\Actions\ListMyReviewAssignmentsAction;
use App\Domains\Library\Actions\ManageReviewerPoolAction;
use App\Domains\Library\Actions\RemindReviewersAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Actions\SubmitResearchReviewAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * RESEARCH_ARTICLES_PLAN R3b (STATUS §5kr): the reviewer's side of peer
 * review — the reviewer pool, due dates and reminders, the
 * conflict-of-interest step, and an inbox that shows the round, the
 * writer's note on a revision and the reviewer's own earlier reports, and
 * never the author, the price or the other reviewers.
 */
function r3bWriter(): User
{
    $user = User::factory()->create(['name' => 'Hidden Author Name']);
    $application = app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => 'Hidden Author Name', 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return $user;
}

function r3bPaper(User $writer): LibraryItem
{
    $item = app(SaveWriterItemAction::class)->execute($writer->id, [
        'title' => 'Monsoon Fisheries', 'content_type' => 'research', 'access_type' => 'paid', 'price' => 99,
        'body' => '<p>The secret findings.</p>', 'abstract' => 'An abstract.',
        'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
    ]);

    return app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id);
}

it('keeps the paper closed until the reviewer declares no conflict of interest, and takes no report before', function () {
    $item = r3bPaper(r3bWriter());
    $reviewer = User::factory()->create(['email' => 'coi@akuru.test']);
    $assignment = app(AssignResearchReviewerAction::class)->execute($item->id, $reviewer->email, User::factory()->create()->id);

    $inbox = app(ListMyReviewAssignmentsAction::class)->execute($reviewer->id)[0];
    expect($inbox['coi_declared'])->toBeFalse()
        ->and($inbox['item']['title'])->toBe('Monsoon Fisheries')
        ->and($inbox['item']['body'])->toBeNull()
        ->and($inbox['item']['abstract'])->toBeNull();
    expect(fn () => app(SubmitResearchReviewAction::class)->execute($reviewer->id, $assignment->id, 'accept'))->toThrow(ValidationException::class);

    // Only their own assignment.
    expect(fn () => app(DeclareReviewerNoConflictAction::class)->execute(User::factory()->create()->id, $assignment->id))->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()->actingAs($reviewer)->post(route('review.declare', $assignment->id))->assertSessionHasNoErrors();
    $inbox = app(ListMyReviewAssignmentsAction::class)->execute($reviewer->id)[0];
    expect($inbox['coi_declared'])->toBeTrue()
        ->and($inbox['item']['body'])->toContain('The secret findings.');

    // Single-blind: nothing about the author, the price or the sales.
    $json = json_encode($inbox);
    expect($json)->not->toContain('Hidden Author Name')->and($json)->not->toContain('99');
});

it('gives each report a due date — 14 days unless the office picks one — and a new round a new one', function () {
    $this->travelTo('2026-10-01 10:00:00');
    $writer = r3bWriter();
    $item = r3bPaper($writer);
    $office = User::factory()->create();
    $one = app(AssignResearchReviewerAction::class)->execute($item->id, User::factory()->create()->email, $office->id);
    $two = app(AssignResearchReviewerAction::class)->execute($item->id, User::factory()->create()->email, $office->id, '2026-10-05');

    expect($one->due_at->timezone('Indian/Maldives')->toDateString())->toBe('2026-10-15')
        ->and($two->due_at->timezone('Indian/Maldives')->toDateString())->toBe('2026-10-05');
    expect(fn () => app(AssignResearchReviewerAction::class)->execute($item->id, User::factory()->create()->email, $office->id, '2026-09-01'))
        ->toThrow(ValidationException::class);

    // A revision opens round 2 with a fresh due date and fresh reminders.
    app(DeclareReviewerNoConflictAction::class)->execute((int) $one->reviewer_user_id, $one->id);
    app(SubmitResearchReviewAction::class)->execute((int) $one->reviewer_user_id, $one->id, 'revise', 'Add a map.');
    $one->forceFill(['reminded_at' => now()])->save();
    $this->travelTo('2026-10-10 10:00:00');
    app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id, 'Added the map on page two.');

    $fresh = $one->fresh();
    expect($fresh->due_at->timezone('Indian/Maldives')->toDateString())->toBe('2026-10-24')
        ->and($fresh->reminded_at)->toBeNull();

    // The reviewer reads the writer's note, and their own earlier report.
    $inbox = collect(app(ListMyReviewAssignmentsAction::class)->execute((int) $one->reviewer_user_id))->firstWhere('id', $one->id);
    expect($inbox['round'])->toBe(2)
        ->and($inbox['revision_note'])->toBe('Added the map on page two.')
        ->and($inbox['my_reports'])->toHaveCount(1)
        ->and($inbox['my_reports'][0])->toMatchArray(['recommendation' => 'revise', 'comment' => 'Add a map.']);
});

it('reminds a reviewer three days before the due date and on the day, once each', function () {
    $this->travelTo('2026-10-01 09:00:00');
    $item = r3bPaper(r3bWriter());
    $reviewer = User::factory()->create();
    app(AssignResearchReviewerAction::class)->execute($item->id, $reviewer->email, User::factory()->create()->id, '2026-10-10');
    $titles = fn () => UserNotification::query()->where('user_id', $reviewer->id)->where('title', 'like', 'Peer review due%')->pluck('title')->all();

    $this->travelTo('2026-10-06 09:00:00');
    expect(app(RemindReviewersAction::class)->execute())->toBe(0);

    $this->travelTo('2026-10-07 09:00:00');
    expect(app(RemindReviewersAction::class)->execute())->toBe(1);
    expect(app(RemindReviewersAction::class)->execute())->toBe(0);
    $this->travelTo('2026-10-09 09:00:00');
    expect(app(RemindReviewersAction::class)->execute())->toBe(0);

    $this->travelTo('2026-10-10 09:00:00');
    expect(app(RemindReviewersAction::class)->execute())->toBe(1);
    $this->travelTo('2026-10-11 09:00:00');
    expect(app(RemindReviewersAction::class)->execute())->toBe(0)
        ->and($titles())->toBe(['Peer review due soon', 'Peer review due today']);

    $this->artisan('library:remind-reviewers')->assertSuccessful();
});

it('does not remind a reviewer who has reported, or while the paper is back with the writer', function () {
    $this->travelTo('2026-10-01 09:00:00');
    $item = r3bPaper(r3bWriter());
    $done = User::factory()->create();
    $waiting = User::factory()->create();
    $a = app(AssignResearchReviewerAction::class)->execute($item->id, $done->email, User::factory()->create()->id, '2026-10-03');
    app(AssignResearchReviewerAction::class)->execute($item->id, $waiting->email, User::factory()->create()->id, '2026-10-03');
    app(DeclareReviewerNoConflictAction::class)->execute($done->id, $a->id);
    app(SubmitResearchReviewAction::class)->execute($done->id, $a->id, 'revise');

    expect(app(RemindReviewersAction::class)->execute())->toBe(0);
});

it('keeps a pool of reviewers the office can add to and remove from, with what each has open and done', function () {
    $admin = actingSystemAdmin(['library.manage']);
    $item = r3bPaper(r3bWriter());
    $busy = User::factory()->create(['name' => 'Busy Reviewer', 'email' => 'busy@akuru.test']);
    $free = User::factory()->create(['name' => 'Free Reviewer', 'email' => 'free@akuru.test']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)->post(route('admin.library.reviewers.store'), ['email' => 'free@akuru.test'])->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($admin)->post(route('admin.library.reviewers.store'), ['email' => 'nobody@akuru.test'])->assertSessionHasErrors('email');
    // Assigning by email adds a reviewer to the pool too.
    app(AssignResearchReviewerAction::class)->execute($item->id, 'busy@akuru.test', $admin->id);

    $pool = collect(app(ManageReviewerPoolAction::class)->list())->keyBy('email');
    expect($pool->keys()->sort()->values()->all())->toBe(['busy@akuru.test', 'free@akuru.test'])
        ->and($pool['busy@akuru.test']['open'])->toBe(1)
        ->and($pool['free@akuru.test']['open'])->toBe(0);

    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.library.reviewers'))
        ->assertInertia(fn ($page) => $page->component('Library/Reviewers')->has('reviewers', 2));
    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.library.reviewers.export'))->streamedContent();
    expect($csv)->toContain('busy@akuru.test');
    // The office's assignment picker offers the pool.
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.library.index'))
        ->assertInertia(fn ($page) => $page->has('options.reviewers', 2));

    // Someone with a report open stays until it is done.
    $this->withoutLocalizationMiddleware()->actingAs($admin)->delete(route('admin.library.reviewers.destroy', $busy->id))->assertSessionHasErrors('reviewer');
    $this->withoutLocalizationMiddleware()->actingAs($admin)->delete(route('admin.library.reviewers.destroy', $free->id))->assertSessionHasNoErrors();
    expect($free->fresh()->hasRole('reviewer'))->toBeFalse()
        ->and($busy->fresh()->hasRole('reviewer'))->toBeTrue();

    // The screen is the office's.
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get(route('admin.library.reviewers'))->assertForbidden();
});
