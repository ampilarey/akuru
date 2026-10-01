<?php

use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Lending\Actions\RemindLendingAction;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Models\LendingLoan;
use App\Domains\Lending\Models\LendingRating;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * LENDING_AND_USED_BOOKS_PLAN L2 (STATUS §5mt): the rest of lending. Daily
 * reminders to both sides — two days before, on the day, every day overdue,
 * each once; ratings of each other after a return, shown to the next
 * borrower and the next lender; a lender pauses themselves or a book; the
 * office pauses a lender or takes a book down with a note; a lenders CSV.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['identity.verification.enforce' => true, 'lending.notices.email' => false, 'lending.notices.sms' => false]);
});

function extrasWeb()
{
    return test()->withoutLocalizationMiddleware();
}

/** A verified lender with one book, and a borrower; optionally the loan already returned. */
function extrasLoan(bool $returned = true): array
{
    $lender = User::factory()->create(['name' => 'Aminath Lender', 'phone' => '+9607771111']);
    $borrower = User::factory()->create(['name' => 'Hassan Borrower', 'phone' => '+9607772222']);
    $office = actingSystemAdmin(['bookshop.manage']);
    $card = fn () => ['id_front' => UploadedFile::fake()->image('f.png'), 'id_back' => UploadedFile::fake()->image('b.png')];
    extrasWeb()->actingAs($lender)->post(route('public.lending.register'), ['display_name' => 'Aminath', 'island' => 'Hithadhoo'])->assertSessionHasNoErrors();
    extrasWeb()->actingAs($lender)->post(route('public.lending.identity'), $card())->assertSessionHasNoErrors();
    extrasWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertRedirect();
    extrasWeb()->actingAs($lender)->post(route('public.lending.books.store'), ['title' => 'Grade 7 Dhivehi reader', 'condition' => 'good', 'max_days' => 14])->assertSessionHasNoErrors();
    $book = LendingBook::query()->where('lender_id', Lender::query()->where('user_id', $lender->id)->value('id'))->sole();
    extrasWeb()->actingAs($borrower)->post(route('public.lending.request', $book->slug), [])->assertSessionHasNoErrors();
    $loan = LendingLoan::query()->where('lending_book_id', $book->id)->sole();
    extrasWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'accept']), ['due_on' => now()->addDays(10)->toDateString()])->assertSessionHasNoErrors();
    extrasWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'handover']), [])->assertSessionHasNoErrors();
    if ($returned) {
        extrasWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'returned']), [])->assertSessionHasNoErrors();
    }
    UserNotification::query()->delete();

    return [$lender, $borrower, $office, $book, $loan->refresh()];
}

it('reminds both sides two days before, on the day and every day overdue — each once', function () {
    [$lender, $borrower, $office, $book, $loan] = extrasLoan(returned: false);
    $due = $loan->due_on->copy();
    $count = fn (int $userId) => UserNotification::query()->where('user_id', $userId)->where('category', 'lending')->count();
    $remind = app(RemindLendingAction::class);

    expect($remind->execute($due->copy()->subDays(5)))->toBe(0);
    expect($remind->execute($due->copy()->subDays(2)))->toBe(2);
    expect($remind->execute($due->copy()->subDays(2)))->toBe(0); // once
    expect($remind->execute($due->copy()->subDay()))->toBe(0); // already reminded before
    expect($remind->execute($due->copy()))->toBe(2);
    expect($remind->execute($due->copy()))->toBe(0);
    expect($remind->execute($due->copy()->addDay()))->toBe(2);
    expect($remind->execute($due->copy()->addDay()))->toBe(0); // once a day
    expect($remind->execute($due->copy()->addDays(2)))->toBe(2);
    expect($count($lender->id))->toBe(4)->and($count($borrower->id))->toBe(4);
    $latest = UserNotification::query()->where('user_id', $borrower->id)->latest('id')->first();
    expect($latest->title)->toBe('A borrowed book is overdue')->and($latest->message)->toContain('2 day(s) ago')->and($latest->message)->toContain('Aminath');
    expect($loan->refresh()->last_overdue_reminder_on->toDateString())->toBe($due->copy()->addDays(2)->toDateString());

    // A returned book is left alone; the command runs the same action.
    extrasWeb()->actingAs($lender)->post(route('public.lending.loan', [$loan->id, 'returned']), [])->assertSessionHasNoErrors();
    expect($remind->execute($due->copy()->addDays(3)))->toBe(0);
    $this->artisan('lending:remind')->expectsOutputToContain('Lending reminders sent: 0')->assertSuccessful();
});

it('lets each side rate the other once after a return, shows the lender\'s stars on the shelf and the borrower\'s to the lender, and tells the rated person', function () {
    [$lender, $borrower, $office, $book, $loan] = extrasLoan();
    $other = User::factory()->create(['name' => 'Nobody']);

    extrasWeb()->actingAs($other)->post(route('public.lending.rate', $loan->id), ['stars' => 5])->assertNotFound();
    extrasWeb()->actingAs($borrower)->post(route('public.lending.rate', $loan->id), ['stars' => 7])->assertSessionHasErrors('stars');
    extrasWeb()->actingAs($borrower)->post(route('public.lending.rate', $loan->id), ['stars' => 4, 'comment' => 'Kind and on time.'])->assertRedirect(route('public.lending.mine').'#borrowing');
    extrasWeb()->actingAs($borrower)->post(route('public.lending.rate', $loan->id), ['stars' => 1])->assertSessionHasErrors('stars');
    extrasWeb()->actingAs($lender)->post(route('public.lending.rate', $loan->id), ['stars' => 5])->assertRedirect(route('public.lending.mine').'#lending');
    expect(LendingRating::query()->count())->toBe(2);
    $byBorrower = LendingRating::query()->where('by_user_id', $borrower->id)->sole();
    expect($byBorrower->about)->toBe('lender')->and((int) $byBorrower->about_user_id)->toBe($lender->id)->and($byBorrower->stars)->toBe(4);
    expect(UserNotification::query()->where('user_id', $lender->id)->where('title', 'You were rated')->count())->toBe(1);
    expect(UserNotification::query()->where('user_id', $borrower->id)->where('title', 'You were rated')->count())->toBe(1);

    // Shown: the lender's stars on the shelf card and the book page with the comment; both ratings on both My lending pages.
    extrasWeb()->get(route('public.lending.index'))->assertOk()->assertSee('data-testid="card-rating" data-avg="4"', false);
    extrasWeb()->get(route('public.lending.show', $book->slug))->assertOk()->assertSee('Kind and on time.')->assertSee('4 of 5 (1)');
    extrasWeb()->actingAs($borrower)->get(route('public.lending.mine'))->assertOk()->assertSee('data-testid="my-rating-'.$loan->id.'"', false)->assertSee('data-testid="their-rating-'.$loan->id.'"', false)->assertDontSee('data-testid="rate-form-'.$loan->id.'"', false);
    extrasWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertOk()->assertSee('data-testid="borrower-rating"', false)->assertSee('5 of 5 (1)');
    extrasWeb()->actingAs($office)->get(route('admin.lending.index'))->assertInertia(fn ($p) => $p->where('admin.lenders.0.rating.avg', 4)->where('admin.lenders.0.rating.count', 1));

    // Not before the return.
    [$lender2, $borrower2, , , $open] = extrasLoan(returned: false);
    extrasWeb()->actingAs($borrower2)->post(route('public.lending.rate', $open->id), ['stars' => 5])->assertSessionHasErrors('stars');
});

it('lets a lender pause themselves or a book, and the office pause a lender or take a book down with a note; exports the lenders', function () {
    [$lender, $borrower, $office, $book, $loan] = extrasLoan();
    $shelf = fn () => extrasWeb()->get(route('public.lending.index'));

    $shelf()->assertSee('Grade 7 Dhivehi reader');
    // The lender pauses a book, then themselves; the shelf empties and refills.
    extrasWeb()->actingAs($lender)->post(route('public.lending.books.status', [$book->id, 'pause']), [])->assertSessionHasNoErrors();
    expect($book->refresh()->status->value)->toBe('paused');
    $shelf()->assertDontSee('Grade 7 Dhivehi reader');
    extrasWeb()->actingAs($lender)->post(route('public.lending.books.status', [$book->id, 'resume']), [])->assertSessionHasNoErrors();
    $shelf()->assertSee('Grade 7 Dhivehi reader');
    extrasWeb()->actingAs($lender)->post(route('public.lending.status', 'pause'), [])->assertRedirect(route('public.lending.mine').'#lender');
    expect(Lender::query()->sole()->status)->toBe('paused');
    $shelf()->assertDontSee('Grade 7 Dhivehi reader');
    extrasWeb()->actingAs($lender)->post(route('public.lending.status', 'resume'), [])->assertSessionHasNoErrors();
    $shelf()->assertSee('Grade 7 Dhivehi reader');
    // Another lender cannot touch this one's book.
    $stranger = User::factory()->create();
    extrasWeb()->actingAs($stranger)->post(route('public.lending.register'), ['display_name' => 'Someone'])->assertSessionHasNoErrors();
    extrasWeb()->actingAs($stranger)->post(route('public.lending.books.status', [$book->id, 'pause']), [])->assertNotFound();

    // The office pauses the lender with a note; the lender reads it and cannot resume; the office resumes.
    $lenderId = Lender::query()->where('user_id', $lender->id)->value('id');
    extrasWeb()->actingAs($office)->post(route('admin.lending.lender', [$lenderId, 'pause']), [])->assertSessionHasErrors('note');
    extrasWeb()->actingAs($office)->post(route('admin.lending.lender', [$lenderId, 'pause']), ['note' => 'Two borrowers reported the books were not as described.'])->assertSessionHasNoErrors();
    $row = Lender::query()->whereKey($lenderId)->sole();
    expect($row->status)->toBe('paused')->and($row->office_paused)->toBeTrue();
    $shelf()->assertDontSee('Grade 7 Dhivehi reader');
    extrasWeb()->actingAs($lender)->get(route('public.lending.mine'))->assertOk()->assertSee('Two borrowers reported')->assertSee('data-office-paused="1"', false);
    extrasWeb()->actingAs($lender)->post(route('public.lending.status', 'resume'), [])->assertSessionHasErrors('status');
    expect(UserNotification::query()->where('user_id', $lender->id)->where('title', 'The office paused your lending')->count())->toBe(1);
    extrasWeb()->actingAs($office)->post(route('admin.lending.lender', [$lenderId, 'resume']), [])->assertSessionHasNoErrors();
    expect(Lender::query()->whereKey($lenderId)->sole()->office_paused)->toBeFalse();
    $shelf()->assertSee('Grade 7 Dhivehi reader');

    // The office takes the book down with a note; the lender is told; the book is gone from their list and the shelf.
    extrasWeb()->actingAs($office)->post(route('admin.lending.books.remove', $book->id), [])->assertSessionHasErrors('note');
    extrasWeb()->actingAs($office)->post(route('admin.lending.books.remove', $book->id), ['note' => 'Copyrighted photocopy.'])->assertSessionHasNoErrors();
    expect($book->refresh()->status->value)->toBe('removed')->and($book->office_note)->toBe('Copyrighted photocopy.');
    $shelf()->assertDontSee('Grade 7 Dhivehi reader');
    expect(UserNotification::query()->where('user_id', $lender->id)->where('title', 'The office took a book down')->value('message'))->toContain('Copyrighted photocopy.');
    extrasWeb()->actingAs($borrower)->post(route('admin.lending.lender', [$lenderId, 'pause']), ['note' => 'x'])->assertForbidden();

    // Lenders CSV.
    $csv = extrasWeb()->actingAs($office)->get(route('admin.lending.lenders.export'))->assertOk();
    expect($csv->streamedContent())->toContain('display_name')->toContain('Aminath')->toContain('Hithadhoo')->toContain('Someone');
});
