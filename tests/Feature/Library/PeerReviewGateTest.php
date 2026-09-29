<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\AssertResearchReviewedAction;
use App\Domains\Library\Actions\AssignResearchReviewerAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\ListWriterDashboardAction;
use App\Domains\Library\Actions\ListWriterQueuesAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibrarySettingsAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Actions\SubmitResearchReviewAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * RESEARCH_ARTICLES_PLAN R3a (STATUS §5kq): peer review is a must (the
 * owner's decision D2). Every publish path refuses research without enough
 * accepts in the current review round; a revision is a new round; the
 * writer and the office read where review stands; and the right people hear
 * about it — the writer never by the reviewer's name.
 */
function r3Writer(): User
{
    $user = User::factory()->create(['name' => 'Writer Person']);
    $application = app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => 'Ustadha Writer', 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return $user;
}

function r3Submitted(User $writer, string $title = 'Coral Study'): LibraryItem
{
    $item = app(SaveWriterItemAction::class)->execute($writer->id, [
        'title' => $title,
        'content_type' => 'research',
        'access_type' => 'free_public',
        'body' => '<p>Findings.</p>',
        'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
    ]);

    return app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id);
}

function r3Reviewer(string $email): User
{
    return User::factory()->create(['email' => $email, 'name' => 'Secret Reviewer '.$email]);
}

it('refuses to publish unreviewed research from the publisher, the office\'s button and the editor\'s approval', function () {
    $item = r3Submitted(r3Writer());
    $admin = actingSystemAdmin(['library.manage']);

    expect(fn () => app(PublishLibraryItemAction::class)->execute($item->id, $admin->id))->toThrow(ValidationException::class);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.publish', $item->id), ['publish' => 1])
        ->assertSessionHasErrors('item');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), ['decision' => 'approved'])
        ->assertSessionHasErrors('item');

    expect($item->fresh()->status->value)->toBe('submitted');
});

it('needs as many accepts as the office asks for, in the current round', function () {
    $item = r3Submitted(r3Writer());
    $admin = actingSystemAdmin(['library.manage']);
    app(SaveLibrarySettingsAction::class)->execute(['research_reviews_required' => 2]);

    $first = app(AssignResearchReviewerAction::class)->execute($item->id, r3Reviewer('one@akuru.test')->email, $admin->id);
    $second = app(AssignResearchReviewerAction::class)->execute($item->id, r3Reviewer('two@akuru.test')->email, $admin->id);
    app(SubmitResearchReviewAction::class)->execute((int) $first->reviewer_user_id, $first->id, 'accept');

    expect(fn () => app(PublishLibraryItemAction::class)->execute($item->id, $admin->id))->toThrow(ValidationException::class)
        ->and(app(AssertResearchReviewedAction::class)->state($item->fresh()))->toMatchArray(['state' => 'with_reviewer', 'accepts' => 1, 'required' => 2]);

    app(SubmitResearchReviewAction::class)->execute((int) $second->reviewer_user_id, $second->id, 'accept');
    expect(app(AssertResearchReviewedAction::class)->state($item->fresh())['state'])->toBe('accepted_awaiting_publish');

    app(PublishLibraryItemAction::class)->execute($item->id, $admin->id);
    expect($item->fresh()->status->value)->toBe('published');
});

it('sends a revision back to the writer, opens round 2 on resubmission, and stops counting an accept from round 1', function () {
    $writer = r3Writer();
    $item = r3Submitted($writer);
    $admin = actingSystemAdmin(['library.manage']);
    $accepter = r3Reviewer('accept@akuru.test');
    $reviser = r3Reviewer('revise@akuru.test');
    $slow = r3Reviewer('slow@akuru.test');
    $a = app(AssignResearchReviewerAction::class)->execute($item->id, $accepter->email, $admin->id);
    $r = app(AssignResearchReviewerAction::class)->execute($item->id, $reviser->email, $admin->id);
    $s = app(AssignResearchReviewerAction::class)->execute($item->id, $slow->email, $admin->id);

    app(SubmitResearchReviewAction::class)->execute($accepter->id, $a->id, 'accept');
    app(SubmitResearchReviewAction::class)->execute($reviser->id, $r->id, 'revise', 'Add the sample sizes.');

    expect($item->fresh()->status->value)->toBe('changes_requested')
        ->and(app(AssertResearchReviewedAction::class)->state($item->fresh())['state'])->toBe('revision_requested');
    // While it is back with the writer nobody reports on it.
    expect(fn () => app(SubmitResearchReviewAction::class)->execute($slow->id, $s->id, 'accept'))->toThrow(ValidationException::class);

    app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id);
    $item->refresh();

    expect($item->review_round)->toBe(2)
        ->and($r->fresh()->only(['status', 'recommendation', 'round']))->toBe(['status' => 'assigned', 'recommendation' => null, 'round' => 2])
        // The reviewer who had not reported reads the revised text too.
        ->and($s->fresh()->round)->toBe(2)
        // The round-1 accept stays where it was, and no longer counts.
        ->and($a->fresh()->only(['status', 'recommendation', 'round']))->toBe(['status' => 'done', 'recommendation' => 'accept', 'round' => 1])
        ->and(app(AssertResearchReviewedAction::class)->acceptsInRound($item))->toBe(0);
    expect(fn () => app(PublishLibraryItemAction::class)->execute($item->id, $admin->id))->toThrow(ValidationException::class);

    // A round-1 report cannot be sent into round 2 either.
    expect(fn () => app(SubmitResearchReviewAction::class)->execute($accepter->id, $a->id, 'accept'))->toThrow(ValidationException::class);

    // Assigning the round-1 accepter again asks them to read the revision.
    app(AssignResearchReviewerAction::class)->execute($item->id, $accepter->email, $admin->id);
    expect($a->fresh()->only(['status', 'round']))->toBe(['status' => 'assigned', 'round' => 2]);

    app(SubmitResearchReviewAction::class)->execute($reviser->id, $r->id, 'accept');
    app(PublishLibraryItemAction::class)->execute($item->id, $admin->id);
    expect($item->fresh()->status->value)->toBe('published');
});

it('tells the reviewer, the writer and the office — and never tells the writer who reviewed', function () {
    $writer = r3Writer();
    $item = r3Submitted($writer);
    $admin = actingSystemAdmin(['library.manage']);
    $reviewer = r3Reviewer('who@akuru.test');
    $assignment = app(AssignResearchReviewerAction::class)->execute($item->id, $reviewer->email, $admin->id);

    $titles = fn (User $user) => UserNotification::query()->where('user_id', $user->id)->orderBy('id')->pluck('title')->all();
    expect($titles($reviewer))->toBe(['Research to review']);

    app(SubmitResearchReviewAction::class)->execute($reviewer->id, $assignment->id, 'revise', 'Tighten the method.');
    $toWriter = UserNotification::query()->where('user_id', $writer->id)->where('title', 'A peer reviewer asked for revisions')->sole();
    expect($toWriter->message)->toContain('Tighten the method.')
        ->and($toWriter->message)->not->toContain('Secret Reviewer')
        ->and($toWriter->message)->not->toContain('who@akuru.test');

    app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id);
    expect($titles($reviewer))->toBe(['Research to review', 'Revised research to review']);

    app(SubmitResearchReviewAction::class)->execute($reviewer->id, $assignment->id, 'accept', 'Good now.');
    expect($titles($admin))->toContain('Research ready to publish')
        ->and(UserNotification::query()->where('user_id', $writer->id)->where('title', 'A peer reviewer accepted your research')->sole()->message)->toContain('Good now.');
});

it('shows the writer and the office where review stands', function () {
    $writer = r3Writer();
    $item = r3Submitted($writer);
    $admin = actingSystemAdmin(['library.manage']);

    $state = fn () => collect(app(ListWriterDashboardAction::class)->execute($writer->id)['items'])->firstWhere('id', $item->id)['review_state'];
    expect($state()['state'])->toBe('awaiting_reviewer')
        ->and(collect(app(ListWriterQueuesAction::class)->execute()['submissions'])->firstWhere('id', $item->id)['review_state']['state'])->toBe('awaiting_reviewer');

    $assignment = app(AssignResearchReviewerAction::class)->execute($item->id, r3Reviewer('x@akuru.test')->email, $admin->id);
    expect($state()['state'])->toBe('with_reviewer');

    app(SubmitResearchReviewAction::class)->execute((int) $assignment->reviewer_user_id, $assignment->id, 'accept');
    expect($state()['state'])->toBe('accepted_awaiting_publish');

    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.library.index'))
        ->assertInertia(fn ($page) => $page->where('queues.submissions.0.review_state.state', 'accepted_awaiting_publish'));
});

it('leaves books and articles to the editor alone', function () {
    $writer = r3Writer();
    $article = app(SaveWriterItemAction::class)->execute($writer->id, ['title' => 'Plain Article', 'content_type' => 'article', 'access_type' => 'free_public', 'body' => '<p>x</p>']);

    app(PublishLibraryItemAction::class)->execute($article->id, User::factory()->create()->id);

    expect($article->fresh()->status->value)->toBe('published')
        ->and(LibraryReviewAssignment::query()->count())->toBe(0)
        ->and(app(AssertResearchReviewedAction::class)->state($article->fresh()))->toBeNull();
});
