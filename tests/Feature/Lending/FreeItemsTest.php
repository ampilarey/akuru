<?php

use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Models\LendingLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * LENDING_AND_USED_BOOKS_PLAN L5 (STATUS §5na): the Free items page. The
 * owner: "Is there any give away or free item page? When it's taken it
 * appears as taken. Lenders can list this." Give-aways have a page of their
 * own; one the giver has promised is Reserved (and refuses new requests);
 * one handed over stays on the page as Taken, with the day, for a while.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['identity.verification.enforce' => true, 'lending.notices.email' => false, 'lending.notices.sms' => false]);
});

function freeWeb()
{
    return test()->withoutLocalizationMiddleware();
}

it('lists give-aways on their own page, marks a promised one Reserved and a handed-over one Taken, and lets a giver start from there', function () {
    $giver = User::factory()->create(['name' => 'Aminath Giver']);
    $taker = User::factory()->create(['name' => 'Hassan Taker']);
    $other = User::factory()->create(['name' => 'Other Person']);
    $office = actingSystemAdmin(['bookshop.manage']);
    $card = fn () => ['id_front' => UploadedFile::fake()->image('f.png'), 'id_back' => UploadedFile::fake()->image('b.png')];
    freeWeb()->actingAs($giver)->post(route('public.lending.register'), ['display_name' => 'Aminath', 'island' => 'Hithadhoo'])->assertSessionHasNoErrors();
    freeWeb()->actingAs($giver)->post(route('public.lending.identity'), $card())->assertSessionHasNoErrors();
    freeWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertRedirect();

    // Empty: says so, and offers the way to give something.
    freeWeb()->get(route('public.lending.free'))->assertOk()->assertSee('data-testid="free-empty"', false)->assertSee('data-testid="free-give-cta"', false);
    // The giver's way in opens My lending's form with Free to keep chosen.
    $form = freeWeb()->actingAs($giver)->get(route('public.lending.mine', ['offer' => 'give']))->assertOk()->getContent();
    preg_match('/<details[^>]*data-testid="add-book"[^>]*>/', $form, $details);
    preg_match('/<input[^>]*name="offer"[^>]*value="give"[^>]*>/', $form, $give);
    expect($details[0] ?? '')->toMatch('/\sopen[\s>]/')->and($give[0] ?? '')->toMatch('/\schecked[\s>]/');

    freeWeb()->actingAs($giver)->post(route('public.lending.books.store'), ['title' => 'School uniform, size 8', 'condition' => 'good', 'offer' => 'give'])->assertSessionHasNoErrors();
    freeWeb()->actingAs($giver)->post(route('public.lending.books.store'), ['title' => 'Grade 7 reader', 'condition' => 'good'])->assertSessionHasNoErrors();
    $gift = LendingBook::query()->where('offer', 'give')->sole();

    // The page lists the give-away, not the loan.
    freeWeb()->get(route('public.lending.free'))->assertOk()->assertSee('School uniform, size 8')->assertDontSee('Grade 7 reader')->assertSee('data-state="available"', false);

    // Asked by two; the giver accepts one: Reserved on the page and the item's page, and new requests refused.
    freeWeb()->actingAs($taker)->post(route('public.lending.request', $gift->slug), [])->assertSessionHasNoErrors();
    $loan = LendingLoan::query()->sole();
    freeWeb()->actingAs($giver)->post(route('public.lending.loan', [$loan->id, 'accept']), [])->assertSessionHasNoErrors();
    freeWeb()->get(route('public.lending.free'))->assertSee('data-state="reserved"', false)->assertSee('data-badge="reserved"', false);
    freeWeb()->get(route('public.lending.show', $gift->slug))->assertOk()->assertSee('data-testid="reserved-note"', false)->assertDontSee('data-testid="ask-form"', false);
    freeWeb()->actingAs($other)->post(route('public.lending.request', $gift->slug), [])->assertSessionHasErrors('book');

    // The taker cancels: free again, and others may ask.
    freeWeb()->actingAs($taker)->post(route('public.lending.loan', [$loan->id, 'cancel']), [])->assertSessionHasNoErrors();
    freeWeb()->get(route('public.lending.free'))->assertSee('data-state="available"', false)->assertDontSee('data-badge="reserved"', false);
    freeWeb()->actingAs($other)->post(route('public.lending.request', $gift->slug), [])->assertSessionHasNoErrors();
    $second = LendingLoan::query()->latest('id')->first();
    freeWeb()->actingAs($giver)->post(route('public.lending.loan', [$second->id, 'accept']), [])->assertSessionHasNoErrors();
    freeWeb()->actingAs($giver)->post(route('public.lending.loan', [$second->id, 'handover']), [])->assertSessionHasNoErrors();

    // Handed over: Taken, with the day, under Recently taken; its page says Taken and offers no request.
    $today = now()->toDateString();
    $page = freeWeb()->get(route('public.lending.free'))->assertOk()->assertSee('data-testid="free-taken"', false)->assertSee('data-state="taken"', false)->assertSee('Taken '.$today);
    expect($page->getContent())->not->toContain('Other Person');
    freeWeb()->get(route('public.lending.show', $gift->slug))->assertOk()->assertSee('data-state="taken"', false)->assertSee('data-testid="taken-note"', false)->assertDontSee('data-testid="ask-form"', false);
    freeWeb()->get(route('public.lending.free'))->assertSee('data-testid="free-empty"', false);

    // After the window it leaves the page, and its page is gone.
    LendingLoan::query()->whereKey($second->id)->update(['handed_at' => now()->subDays(31)]);
    freeWeb()->get(route('public.lending.free'))->assertDontSee('School uniform, size 8');
    freeWeb()->get(route('public.lending.show', $gift->slug))->assertNotFound();
});
