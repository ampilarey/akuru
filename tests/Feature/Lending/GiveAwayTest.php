<?php

use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Models\LendingLoan;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * LENDING_AND_USED_BOOKS_PLAN L3 (STATUS §5mv): free books, given away.
 * The owner, 2026-10-01: "I don't see free book, give away". A listed book
 * is offered to lend (L1) or free to keep: a give-away has no return date
 * or deposit, the shelf says so and can be narrowed to it, and handing it
 * over closes the loan as *given* with the book off the shelf for good —
 * nothing comes back, yet both may still rate each other.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['identity.verification.enforce' => true, 'lending.notices.email' => false, 'lending.notices.sms' => false]);
});

function giveWeb()
{
    return test()->withoutLocalizationMiddleware();
}

it('offers a book free to keep: no due date, the shelf and its filter say so, handover gives it away for good, and both may still rate', function () {
    $giver = User::factory()->create(['name' => 'Aminath Giver', 'phone' => '+9607771111']);
    $taker = User::factory()->create(['name' => 'Hassan Taker', 'phone' => '+9607772222']);
    $office = actingSystemAdmin(['bookshop.manage']);
    $card = fn () => ['id_front' => UploadedFile::fake()->image('f.png'), 'id_back' => UploadedFile::fake()->image('b.png')];
    giveWeb()->actingAs($giver)->post(route('public.lending.register'), ['display_name' => 'Aminath', 'island' => 'Hithadhoo'])->assertSessionHasNoErrors();
    giveWeb()->actingAs($giver)->post(route('public.lending.identity'), $card())->assertSessionHasNoErrors();
    giveWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertRedirect();

    // A bad offer is refused; a give-away and a loan are listed side by side.
    giveWeb()->actingAs($giver)->post(route('public.lending.books.store'), ['title' => 'Old atlas', 'condition' => 'fair', 'offer' => 'sell'])->assertSessionHasErrors('offer');
    giveWeb()->actingAs($giver)->post(route('public.lending.books.store'), ['title' => 'Old atlas', 'condition' => 'fair', 'offer' => 'give'])->assertSessionHasNoErrors();
    giveWeb()->actingAs($giver)->post(route('public.lending.books.store'), ['title' => 'Grade 7 reader', 'condition' => 'good'])->assertSessionHasNoErrors();
    $gift = LendingBook::query()->where('slug', 'old-atlas')->sole();
    $loanBook = LendingBook::query()->where('slug', 'grade-7-reader')->sole();
    expect($gift->offer->value)->toBe('give')->and($loanBook->offer->value)->toBe('lend');

    // The shelf: both show; the give-away carries its badge and the Free books chip narrows to it.
    giveWeb()->get(route('public.lending.index'))->assertOk()->assertSee('Old atlas')->assertSee('Grade 7 reader')->assertSee('data-badge="give"', false)->assertSee('data-testid="lending-free-chip"', false);
    giveWeb()->get(route('public.lending.index', ['offer' => 'give']))->assertOk()->assertSee('Old atlas')->assertDontSee('Grade 7 reader');
    giveWeb()->get(route('public.lending.index', ['offer' => 'lend']))->assertOk()->assertDontSee('Old atlas')->assertSee('Grade 7 reader');
    giveWeb()->get(route('public.lending.show', 'old-atlas'))->assertOk()->assertSee('data-offer="give"', false)->assertSee('Ask for it')->assertDontSee('data-testid="book-deposit"', false);
    giveWeb()->get(route('public.lending.show', 'grade-7-reader'))->assertOk()->assertSee('data-offer="lend"', false)->assertSee('Ask to borrow')->assertSee('data-testid="book-deposit"', false);

    // Asked, accepted (no due date even if one is sent), handed over: given, off the shelf, the taker told it is theirs.
    giveWeb()->actingAs($taker)->post(route('public.lending.request', 'old-atlas'), ['message' => 'For my little brother.'])->assertSessionHasNoErrors();
    $loan = LendingLoan::query()->sole();
    giveWeb()->actingAs($giver)->post(route('public.lending.loan', [$loan->id, 'accept']), ['due_on' => now()->addDays(5)->toDateString()])->assertSessionHasNoErrors();
    expect($loan->refresh()->status->value)->toBe('accepted')->and($loan->due_on)->toBeNull();
    expect(UserNotification::query()->where('user_id', $taker->id)->latest('id')->value('message'))->toContain('will give you')->toContain('+9607771111');
    giveWeb()->actingAs($giver)->post(route('public.lending.loan', [$loan->id, 'handover']), [])->assertRedirect(route('public.lending.mine').'#lending')->assertSessionHas('success', 'Handed over. The book is theirs now.');
    expect($loan->refresh()->status->value)->toBe('given')->and($loan->handed_at)->not->toBeNull()->and($gift->refresh()->status->value)->toBe('given');
    expect(UserNotification::query()->where('user_id', $taker->id)->latest('id')->value('title'))->toBe('The book is yours');
    giveWeb()->get(route('public.lending.index'))->assertDontSee('Old atlas');
    // L5: a taken give-away stays visible as Taken on its page (and on Free items), with no request form.
    giveWeb()->get(route('public.lending.show', 'old-atlas'))->assertOk()->assertSee('data-state="taken"', false)->assertDontSee('data-testid="ask-form"', false);
    // Nothing comes back: "returned" is refused; the giver cannot take the book down or re-offer it; both may rate.
    giveWeb()->actingAs($giver)->post(route('public.lending.loan', [$loan->id, 'returned']), [])->assertSessionHasErrors('loan');
    giveWeb()->actingAs($giver)->delete(route('public.lending.books.destroy', $gift->id))->assertSessionHasNoErrors(); // taking a given book off one's own list is allowed
    giveWeb()->actingAs($taker)->post(route('public.lending.rate', $loan->id), ['stars' => 5, 'comment' => 'Thank you!'])->assertSessionHasNoErrors();
    giveWeb()->actingAs($giver)->post(route('public.lending.rate', $loan->id), ['stars' => 5])->assertSessionHasNoErrors();
    giveWeb()->actingAs($taker)->get(route('public.lending.mine'))->assertOk()->assertSee('Given by')->assertSee('data-testid="my-rating-'.$loan->id.'"', false);
    giveWeb()->actingAs($giver)->get(route('public.lending.mine'))->assertOk()->assertSee('data-testid="their-rating-'.$loan->id.'"', false);

    // The office sees the given loan and the lender's stars.
    giveWeb()->actingAs($office)->get(route('admin.lending.index'))->assertInertia(fn ($p) => $p->where('admin.loans.0.status', 'given')->where('admin.lenders.0.rating.avg', 5));

    // A loan book is still a loan: accepted with a due date, out at handover.
    giveWeb()->actingAs($taker)->post(route('public.lending.request', 'grade-7-reader'), [])->assertSessionHasNoErrors();
    $second = LendingLoan::query()->latest('id')->first();
    giveWeb()->actingAs($giver)->post(route('public.lending.loan', [$second->id, 'accept']), [])->assertSessionHasNoErrors();
    giveWeb()->actingAs($giver)->post(route('public.lending.loan', [$second->id, 'handover']), [])->assertSessionHas('success', 'Marked as handed over. The book is on loan.');
    expect($second->refresh()->status->value)->toBe('out')->and($second->due_on)->not->toBeNull()->and($loanBook->refresh()->status->value)->toBe('on_loan');
    expect(Lender::query()->count())->toBe(1);
});
