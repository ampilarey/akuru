<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function approveResearchWriter(User $user): WriterProfile
{
    $application = app(ApplyAsWriterAction::class)->execute($user->id, [
        'display_name' => 'Researcher',
        'agreement_accepted' => true,
    ]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return WriterProfile::query()->where('user_id', $user->id)->firstOrFail();
}

it('walks the research peer-review loop: assign → revise → accept → publish', function () {
    $writerUser = User::factory()->create();
    approveResearchWriter($writerUser);
    $admin = actingSystemAdmin(['library.manage']);
    $reviewerUser = User::factory()->create(['email' => 'reviewer@akuru.test']);

    // Writer submits research with citations.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), [
            'title' => 'Tajweed Pedagogy Study',
            'content_type' => 'research',
            'access_type' => 'free_login',
            'body' => 'Findings',
            'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
            'citations' => 'Al-Jazari, Kitab al-Tajwid.',
        ])->assertSessionHasNoErrors();
    $item = LibraryItem::query()->firstOrFail();
    expect($item->citations)->toContain('Al-Jazari');
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();

    // Editor cannot publish research without a peer accept.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), ['decision' => 'approved'])
        ->assertSessionHasErrors('item');
    expect($item->fresh()->status->value)->toBe('submitted');

    // Assign the reviewer by email — role lands on the unified identity.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.assign-reviewer', $item->id), [
            'reviewer_email' => 'reviewer@akuru.test',
        ])->assertSessionHasNoErrors();
    $assignment = LibraryReviewAssignment::query()->firstOrFail();
    expect($reviewerUser->fresh()->hasRole('reviewer'))->toBeTrue();

    // A stranger cannot answer someone else's assignment.
    $stranger = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->post(route('review.store', $assignment->id), ['recommendation' => 'accept'])
        ->assertSessionHasErrors('assignment');

    // Reviewer asks for a revision — the comment reaches the writer's trail.
    $this->withoutLocalizationMiddleware()->actingAs($reviewerUser)
        ->post(route('review.store', $assignment->id), [
            'recommendation' => 'revise',
            'comment' => 'Method section needs sample sizes.',
        ])->assertSessionHasNoErrors();
    expect($assignment->fresh()->recommendation)->toBe('revise');

    // R3: a revision sends it back to the writer; nothing publishes it, and
    // the reviewer cannot report again until the writer resubmits.
    expect($item->fresh()->status->value)->toBe('changes_requested');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), ['decision' => 'approved'])
        ->assertSessionHasErrors('item');
    $this->withoutLocalizationMiddleware()->actingAs($reviewerUser)
        ->post(route('review.store', $assignment->id), ['recommendation' => 'accept'])
        ->assertSessionHasErrors('assignment');

    // The writer resubmits: round 2, and the same reviewer is asked again.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();
    expect($item->fresh()->review_round)->toBe(2)
        ->and($assignment->fresh()->status)->toBe('assigned')
        ->and($assignment->fresh()->round)->toBe(2);
    $this->withoutLocalizationMiddleware()->actingAs($reviewerUser)
        ->post(route('review.store', $assignment->id), ['recommendation' => 'accept'])
        ->assertSessionHasNoErrors();

    // Editor publishes; citations appear on the public page.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), ['decision' => 'approved'])
        ->assertSessionHasNoErrors();
    expect($item->fresh()->status->value)->toBe('published');
    $this->withoutLocalizationMiddleware()
        ->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee('Al-Jazari');
});

it('publishes non-research without any reviewer, and never research without one', function () {
    $writerUser = User::factory()->create();
    approveResearchWriter($writerUser);
    $admin = actingSystemAdmin(['library.manage']);

    // Articles never need a peer reviewer.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), [
            'title' => 'Simple Article',
            'content_type' => 'article',
            'body' => 'Text',
            'declarations' => ['copyright' => 1],
        ]);
    $article = LibraryItem::query()->firstOrFail();
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.submit', $article->id));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $article->id), ['decision' => 'approved'])
        ->assertSessionHasNoErrors();
    expect($article->fresh()->status->value)->toBe('published');

    // R3 (D2): peer review cannot be turned off — not even by a config
    // value of 0, which reads as 1.
    config()->set('library.research_reviews_required', 0);
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), [
            'title' => 'Fast Research',
            'content_type' => 'research',
            'body' => 'Quick findings',
            'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
        ]);
    $research = LibraryItem::query()->where('title', 'Fast Research')->firstOrFail();
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.submit', $research->id));
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $research->id), ['decision' => 'approved'])
        ->assertSessionHasErrors('item');
    expect($research->fresh()->status->value)->toBe('submitted');
});
