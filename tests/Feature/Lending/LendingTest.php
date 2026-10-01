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
 * LENDING_AND_USED_BOOKS_PLAN L1 (STATUS §5ms): book lending between
 * people. A person registers as a lender and sends their ID card; their
 * books reach the public shelf once the office has checked it (D5); a
 * signed-in person asks for a book; the lender accepts (phones are shared
 * then, not before), hands it over and marks it returned; no money passes
 * through Akuru (D4); the office sees lenders, cards and loans (D6).
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['identity.verification.enforce' => true, 'lending.notices.email' => false, 'lending.notices.sms' => false]);
});

function lendWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function lendCard(): array
{
    return ['id_front' => UploadedFile::fake()->image('front.png', 600, 400), 'id_back' => UploadedFile::fake()->image('back.png', 600, 400)];
}

/** A lender with a checked card and one book on the shelf. */
function checkedLender(array $lenderData = []): array
{
    $lender = User::factory()->create(['name' => 'Aminath Lender', 'phone' => '+9607771111']);
    $office = actingSystemAdmin(['bookshop.manage']);
    lendWeb()->actingAs($lender)->post(route('public.lending.register'), $lenderData + ['display_name' => 'Aminath', 'island' => 'Hithadhoo'])->assertSessionHasNoErrors();
    lendWeb()->actingAs($lender)->post(route('public.lending.identity'), lendCard())->assertSessionHasNoErrors();
    lendWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertRedirect()->assertSessionHasNoErrors();
    lendWeb()->actingAs($lender)->post(route('public.lending.books.store'), ['title' => 'Grade 7 Dhivehi reader', 'author' => 'MoE', 'condition' => 'good', 'grade' => '7', 'subject' => 'Dhivehi', 'max_days' => 21, 'deposit' => 'Another book while mine is out'])->assertSessionHasNoErrors();

    return [$lender, $office, LendingBook::query()->sole()];
}

it('puts a lender\'s books on the shelf only once the office has checked their ID card', function () {
    $lender = User::factory()->create(['name' => 'Aminath Lender']);

    // Not registered: the book form is refused.
    lendWeb()->actingAs($lender)->post(route('public.lending.books.store'), ['title' => 'Atlas', 'condition' => 'good'])->assertForbidden();

    lendWeb()->actingAs($lender)->post(route('public.lending.register'), ['display_name' => 'Aminath', 'island' => 'Hithadhoo', 'about' => 'Teacher.', 'id_required' => 1])->assertRedirect(route('public.lending.mine').'#lender');
    $row = Lender::query()->sole();
    expect($row->display_name)->toBe('Aminath')->and($row->id_required)->toBeTrue()->and($row->status)->toBe('active');

    lendWeb()->actingAs($lender)->post(route('public.lending.books.store'), ['title' => 'Atlas of the Maldives', 'condition' => 'fair', 'max_days' => 500])->assertSessionHasErrors('max_days');
    lendWeb()->actingAs($lender)->post(route('public.lending.books.store'), ['title' => 'Atlas of the Maldives', 'condition' => 'fair', 'max_days' => 30])->assertSessionHasNoErrors();
    $book = LendingBook::query()->sole();
    expect($book->slug)->toBe('atlas-of-the-maldives')->and($book->max_days)->toBe(30)->and($book->condition->value)->toBe('fair');

    // Card not checked: off the shelf, the page a 404, My lending says why.
    lendWeb()->get(route('public.lending.index'))->assertOk()->assertDontSee('Atlas of the Maldives')->assertSee('data-testid="lending-empty"', false);
    lendWeb()->get(route('public.lending.show', 'atlas-of-the-maldives'))->assertNotFound();
    lendWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertOk()->assertSee('data-id-status="none"', false)->assertSee('Atlas of the Maldives');

    // Card sent, then checked by the office: on the shelf, with the lender's name and island and never their phone.
    $office = actingSystemAdmin(['bookshop.manage']);
    lendWeb()->actingAs($lender)->post(route('public.lending.identity'), lendCard())->assertSessionHasNoErrors();
    expect(IdentityVerification::query()->sole()->purpose)->toBe('lender');
    lendWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertSee('data-id-status="pending"', false);
    lendWeb()->actingAs($office)->get(route('admin.lending.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Lending/Admin')->has('admin.identity', 1)->where('admin.lenders.0.id_verified', false)->where('admin.counts.lenders', 1)->where('admin.counts.books', 1));
    lendWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->sole()->id), ['decision' => 'verify'])->assertRedirect()->assertSessionHasNoErrors();

    $shelf = lendWeb()->get(route('public.lending.index'))->assertOk()->assertSee('Atlas of the Maldives')->assertSee('Aminath')->assertSee('Hithadhoo');
    expect($shelf->getContent())->not->toContain($lender->email);
    lendWeb()->get(route('public.lending.show', 'atlas-of-the-maldives'))->assertOk()->assertSee('data-testid="id-required"', false)->assertSee('Fair');
    lendWeb()->get(route('public.lending.index', ['grade' => '9']))->assertSee('data-testid="lending-empty"', false);
    lendWeb()->actingAs($office)->get(route('admin.lending.index'))->assertInertia(fn ($p) => $p->where('admin.lenders.0.id_verified', true));

    // Taking the book down empties the shelf again; the slug is a 404.
    lendWeb()->actingAs($lender)->delete(route('public.lending.books.destroy', $book->id))->assertSessionHasNoErrors();
    expect($book->refresh()->status->value)->toBe('removed');
    lendWeb()->get(route('public.lending.show', 'atlas-of-the-maldives'))->assertNotFound();
});

it('runs a loan from request to return, sharing phones only once the lender accepts, and tells each side in the app', function () {
    [$lender, $office, $book] = checkedLender();
    $borrower = User::factory()->create(['name' => 'Hassan Borrower', 'phone' => '+9607772222']);
    $other = User::factory()->create(['name' => 'Other Person']);

    // The lender cannot borrow their own; a visitor is sent to sign in; the borrower asks once.
    lendWeb()->actingAs($lender)->post(route('public.lending.request', $book->slug), [])->assertSessionHasErrors('book');
    lendWeb()->post(route('public.lending.request', $book->slug), [])->assertRedirect();
    lendWeb()->actingAs($borrower)->post(route('public.lending.request', $book->slug), ['message' => 'I can collect it on Thursday.'])->assertRedirect(route('public.lending.mine').'#borrowing');
    lendWeb()->actingAs($borrower)->post(route('public.lending.request', $book->slug), [])->assertSessionHasErrors('book');
    lendWeb()->actingAs($other)->post(route('public.lending.request', $book->slug), [])->assertSessionHasNoErrors();
    $loan = LendingLoan::query()->where('borrower_user_id', $borrower->id)->sole();
    expect($loan->status->value)->toBe('requested')->and($loan->message)->toBe('I can collect it on Thursday.')->and(LendingLoan::query()->count())->toBe(2);
    expect(UserNotification::query()->where('user_id', $lender->id)->where('category', 'lending')->count())->toBe(2);

    // Before acceptance neither side sees the other's phone.
    lendWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertOk()->assertSee('Hassan Borrower')->assertDontSee('+9607772222');
    lendWeb()->actingAs($borrower)->get(route('public.lending.mine'))->assertOk()->assertDontSee('+9607771111');

    // Only the lender may decide; a decline needs a note; the past is not a return date.
    lendWeb()->actingAs($borrower)->post(route('public.lending.loan', [$loan->id, 'accept']), [])->assertNotFound();
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'decline']), [])->assertSessionHasErrors('note');
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'accept']), ['due_on' => now()->subDay()->toDateString()])->assertSessionHasErrors('due_on');
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'accept']), [])->assertRedirect(route('public.lending.mine').'#lending');
    $loan->refresh();
    expect($loan->status->value)->toBe('accepted')->and($loan->due_on->toDateString())->toBe(now()->addDays(21)->toDateString());
    // The other open request for the same book is declined, with a note, and that person told.
    $otherLoan = LendingLoan::query()->where('borrower_user_id', $other->id)->sole();
    expect($otherLoan->status->value)->toBe('declined')->and($otherLoan->note)->toBe('The book was lent to someone else.');
    expect(UserNotification::query()->where('user_id', $other->id)->where('category', 'lending')->count())->toBe(1);
    expect(UserNotification::query()->where('user_id', $borrower->id)->where('category', 'lending')->latest('id')->value('message'))->toContain('+9607771111');

    // Now both see the phones.
    lendWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertSee('+9607772222');
    lendWeb()->actingAs($borrower)->get(route('public.lending.mine'))->assertSee('+9607771111')->assertSee('Another book while mine is out');

    // Handed over: the book is on loan (still listed, marked so; a new request is refused). Returned: available again.
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'returned']), [])->assertSessionHasErrors('loan');
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'handover']), [])->assertSessionHasNoErrors();
    expect($loan->refresh()->status->value)->toBe('out')->and($book->refresh()->status->value)->toBe('on_loan');
    lendWeb()->get(route('public.lending.index'))->assertSee('data-status="on_loan"', false);
    lendWeb()->actingAs($other)->post(route('public.lending.request', $book->slug), [])->assertSessionHasErrors('book');
    lendWeb()->actingAs($lender)->delete(route('public.lending.books.destroy', $book->id))->assertSessionHasErrors('book');
    lendWeb()->actingAs($borrower)->post(route('public.lending.loan', [$loan->id, 'cancel']), [])->assertSessionHasErrors('loan');
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'returned']), [])->assertSessionHasNoErrors();
    expect($loan->refresh()->status->value)->toBe('returned')->and($loan->returned_at)->not->toBeNull()->and($book->refresh()->status->value)->toBe('available');

    // The office sees it all and exports it; the Bookstore permission is the gate.
    lendWeb()->actingAs($office)->get(route('admin.lending.index'))->assertInertia(fn ($p) => $p->has('admin.loans', 2)->where('admin.loans.0.status', 'declined')->where('admin.loans.1.status', 'returned')->where('admin.loans.1.borrower_phone', '+9607772222'));
    $csv = lendWeb()->actingAs($office)->get(route('admin.lending.export'))->assertOk();
    expect($csv->streamedContent())->toContain('Grade 7 Dhivehi reader')->toContain('returned');
    lendWeb()->actingAs($borrower)->get(route('admin.lending.index'))->assertForbidden();
});

it('lets a borrower cancel while the book is not yet handed over, and refuses a borrower without a checked ID when the lender asks for one', function () {
    [$lender, $office, $book] = checkedLender(['id_required' => 1]);
    $borrower = User::factory()->create(['name' => 'Hassan Borrower']);

    lendWeb()->actingAs($borrower)->post(route('public.lending.request', $book->slug), [])->assertSessionHasErrors('book');
    lendWeb()->actingAs($borrower)->post(route('public.lending.identity'), lendCard())->assertSessionHasNoErrors();
    lendWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertRedirect()->assertSessionHasNoErrors();
    lendWeb()->actingAs($borrower)->post(route('public.lending.request', $book->slug), [])->assertSessionHasNoErrors();
    $loan = LendingLoan::query()->sole();
    expect($loan->academic_year_id)->toBeNull(); // no academic year seeded here; the column is carried (rule 10)

    lendWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertSee('ID checked');
    lendWeb()->actingAs($borrower)->post(route('public.lending.loan', [$loan->id, 'cancel']), [])->assertRedirect(route('public.lending.mine').'#borrowing');
    expect($loan->refresh()->status->value)->toBe('cancelled')->and($book->refresh()->status->value)->toBe('available');
    expect(UserNotification::query()->where('user_id', $lender->id)->where('category', 'lending')->latest('id')->value('title'))->toBe('A request was cancelled');

    // A cancelled request can be asked again; a lender may decline with a note.
    lendWeb()->actingAs($borrower)->post(route('public.lending.request', $book->slug), [])->assertSessionHasNoErrors();
    $again = LendingLoan::query()->latest('id')->first();
    lendWeb()->actingAs($lender)->post(route('public.lending.loan', [$again->id, 'decline']), ['note' => 'Keeping it for my class this term.'])->assertSessionHasNoErrors();
    expect($again->refresh()->status->value)->toBe('declined');
    lendWeb()->actingAs($borrower)->get(route('public.lending.mine'))->assertSee('Keeping it for my class this term.');
});
