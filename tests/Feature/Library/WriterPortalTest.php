<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\ListWriterDashboardAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function approveWriter(User $user, string $name = 'Ustadh Ali'): WriterProfile
{
    $application = app(ApplyAsWriterAction::class)->execute($user->id, [
        'display_name' => $name,
        'agreement_accepted' => true,
    ]);
    $decider = User::factory()->create();
    app(DecideWriterApplicationAction::class)->execute($application->id, $decider->id, true);

    return WriterProfile::query()->where('user_id', $user->id)->firstOrFail();
}

it('walks apply → approve → draft → review loop → publish', function () {
    $writerUser = User::factory()->create();
    $admin = actingPeopleAdmin(['library.manage']);

    // Apply with the agreement accepted.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.apply'), [
            'display_name' => 'Ustadh Ali',
            'expertise' => 'Fiqh',
            'agreement_accepted' => '1',
        ])->assertSessionHasNoErrors();
    $application = WriterApplication::query()->firstOrFail();
    expect($application->status)->toBe('pending')
        ->and($application->agreement_accepted_at)->not->toBeNull();

    // Admin approves — profile + writer role on the unified identity.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.applications.decide', $application->id), ['approve' => 1])
        ->assertSessionHasNoErrors();
    $profile = WriterProfile::query()->where('user_id', $writerUser->id)->firstOrFail();
    expect($writerUser->fresh()->hasRole('writer'))->toBeTrue()
        ->and($profile->display_name)->toBe('Ustadh Ali');

    // Writer saves a paid draft with page breaks.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), [
            'title' => 'Fiqh of Fasting',
            'content_type' => 'book',
            'access_type' => 'paid',
            'price' => 45,
            'body' => 'Page one<!-- pagebreak -->Page two',
        ])->assertSessionHasNoErrors();
    $item = LibraryItem::query()->firstOrFail();
    expect($item->status->value)->toBe('draft')
        ->and((int) $item->writer_id)->toBe($profile->id)
        ->and((string) $item->price)->toBe('45.00')
        ->and($item->page_count)->toBe(2);

    // Submit → editor requests changes with a comment.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();
    expect($item->fresh()->status->value)->toBe('submitted');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), [
            'decision' => 'changes_requested',
            'comment' => 'Add references',
        ])->assertSessionHasNoErrors();
    expect($item->fresh()->status->value)->toBe('changes_requested');
    $dashboard = app(ListWriterDashboardAction::class)->execute($writerUser->id);
    expect($dashboard['items'][0]['latest_comment'])->toBe('Add references');

    // Writer revises, resubmits; editor approves → published + approved_by.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->put(route('write.items.update', $item->id), [
            'title' => 'Fiqh of Fasting',
            'content_type' => 'book',
            'access_type' => 'paid',
            'price' => 45,
            'body' => 'Page one revised<!-- pagebreak -->Page two',
        ])->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->status->value)->toBe('published')
        ->and((int) $item->approved_by)->toBe($admin->id)
        ->and($item->published_at)->not->toBeNull();
    expect(LibraryItemReview::query()->count())->toBe(4); // submitted, changes, submitted, approved

    // The published item is publicly visible.
    $this->withoutLocalizationMiddleware()
        ->get(route('public.library.show', $item->slug))
        ->assertOk();
});

it('guards writer boundaries: no profile, wrong owner, wrong state, double decide', function () {
    $stranger = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->post(route('write.items.store'), [
            'title' => 'Sneaky',
            'content_type' => 'article',
        ])->assertSessionHasErrors('writer');

    $writerOne = User::factory()->create();
    approveWriter($writerOne, 'Writer One');
    $this->withoutLocalizationMiddleware()->actingAs($writerOne)
        ->post(route('write.items.store'), [
            'title' => 'Owned Item',
            'content_type' => 'article',
            'body' => 'Text',
        ]);
    $item = LibraryItem::query()->firstOrFail();

    // Another writer cannot edit it.
    $writerTwo = User::factory()->create();
    approveWriter($writerTwo, 'Writer Two');
    $this->withoutLocalizationMiddleware()->actingAs($writerTwo)
        ->put(route('write.items.update', $item->id), [
            'title' => 'Hijack',
            'content_type' => 'article',
        ])->assertSessionHasErrors('item');

    // Once submitted, even the owner cannot edit.
    $this->withoutLocalizationMiddleware()->actingAs($writerOne)
        ->post(route('write.items.submit', $item->id));
    $this->withoutLocalizationMiddleware()->actingAs($writerOne)
        ->put(route('write.items.update', $item->id), [
            'title' => 'Late edit',
            'content_type' => 'article',
        ])->assertSessionHasErrors('item');

    // A decided application cannot be decided twice.
    $application = WriterApplication::query()->where('user_id', $writerOne->id)->firstOrFail();
    $admin = User::factory()->create();
    expect(fn () => app(DecideWriterApplicationAction::class)->execute($application->id, $admin->id, false))
        ->toThrow(ValidationException::class);
});

it('shows the writer aggregate sales only for their own items', function () {
    $writerUser = User::factory()->create();
    $profile = approveWriter($writerUser);
    $item = LibraryItem::query()->create([
        'title' => 'Seller',
        'slug' => 'seller',
        'content_type' => 'book',
        'access_type' => 'paid',
        'price' => 45,
        'status' => 'published',
        'writer_id' => $profile->id,
    ]);
    foreach ([1, 2] as $i) {
        LibraryPurchase::query()->create([
            'user_id' => User::factory()->create()->id,
            'library_item_id' => $item->id,
            'amount' => 45,
            'currency' => 'MVR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);
    }
    // Someone else's sale never leaks in.
    $otherItem = LibraryItem::query()->create([
        'title' => 'Other', 'slug' => 'other', 'content_type' => 'book',
        'access_type' => 'paid', 'price' => 100, 'status' => 'published',
    ]);
    LibraryPurchase::query()->create([
        'user_id' => User::factory()->create()->id,
        'library_item_id' => $otherItem->id,
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'paid',
        'purchased_at' => now(),
    ]);

    $dashboard = app(ListWriterDashboardAction::class)->execute($writerUser->id);
    expect($dashboard['sales']['total_sales'])->toBe(2)
        ->and($dashboard['sales']['total_revenue'])->toBe(90.0)
        ->and($dashboard['items'][0]['sales'])->toBe(2);
});
