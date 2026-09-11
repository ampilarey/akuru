<?php

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Circulation\Actions\AddBookCopiesAction;
use App\Domains\Circulation\Actions\BulkIssueTextbooksAction;
use App\Domains\Circulation\Actions\BulkReturnTextbooksAction;
use App\Domains\Circulation\Actions\LendCopyAction;
use App\Domains\Circulation\Actions\ListCirculationAction;
use App\Domains\Circulation\Actions\ListLoansAction;
use App\Domains\Circulation\Actions\ReturnCopyAction;
use App\Domains\Circulation\Actions\SaveBookTitleAction;
use App\Domains\Circulation\Enums\CopyStatus;
use App\Domains\Circulation\Enums\LoanStatus;
use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\Loan;
use App\Domains\Circulation\Support\Code39;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E16 — physical circulation.
 *
 * **Not the L-track Library**, which is a digital reader and bookstore. The
 * plan asks for the two never to be confused in code or nav, and one test
 * below asserts that separation rather than trusting it.
 */
function circulationSetup(): array
{
    makeYear(['name' => 'Circulation year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);

    Role::findOrCreate('admin', 'web');
    $librarian = User::factory()->create(['name' => 'Librarian']);
    $librarian->assignRole('admin');

    $title = app(SaveBookTitleAction::class)->execute([
        'title' => 'Fathuruveri', 'author' => 'Anonymous', 'loan_days' => 14,
    ]);

    return [
        'librarian' => $librarian->fresh(),
        'title' => $title,
        'studentA' => makeStudent(['first_name' => 'Hassan', 'last_name' => 'Ali']),
        'studentB' => makeStudent(['first_name' => 'Aishath', 'last_name' => 'Ibrahim']),
    ];
}

it('keeps circulation separate from the digital Library', function () {
    // The plan: "Name it distinctly (Circulation) so it never gets confused
    // with the L-track Library in code or nav."
    expect(Schema::hasTable('book_titles'))->toBeTrue()
        ->and(Schema::hasTable('book_copies'))->toBeTrue()
        ->and(Schema::hasTable('loans'))->toBeTrue();

    $names = collect(app('router')->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->filter(fn (string $n) => str_starts_with($n, 'circulation.'));

    expect($names)->not->toBeEmpty()
        // Circulation lives at its own prefix, never under the Library's.
        ->and($names->filter(fn ($n) => str_contains($n, 'library')))->toHaveCount(0);
});

it('allocates accession numbers without ever reusing one', function () {
    // An accession number is a permanent identifier for a physical object.
    // Reissuing one makes an old loan record point at a different book.
    ['title' => $title] = circulationSetup();

    $first = app(AddBookCopiesAction::class)->execute($title->id, 3);
    expect($first)->toHaveCount(3)
        ->and($first->pluck('accession_number')->all())->toBe(['AK000001', 'AK000002', 'AK000003']);

    // Withdraw one, then add more: the counter continues past the highest ever
    // issued rather than counting what survives.
    BookCopy::query()->where('accession_number', 'AK000002')->update(['status' => CopyStatus::Withdrawn->value]);

    $more = app(AddBookCopiesAction::class)->execute($title->id, 2);
    expect($more->pluck('accession_number')->all())->toBe(['AK000004', 'AK000005']);

    expect(fn () => app(AddBookCopiesAction::class)->execute($title->id, 0))
        ->toThrow(ValidationException::class);
});

it('lends a copy and sets its due date from the title', function () {
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    $copy = app(AddBookCopiesAction::class)->execute($title->id, 1)->first();

    $loan = app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $student->id);

    expect($loan->status)->toBe(LoanStatus::Out)
        ->and($loan->due_on->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and((int) $loan->academic_year_id)->toBeGreaterThan(0)
        ->and($copy->refresh()->status)->toBe(CopyStatus::OnLoan);
});

it('refuses to lend the same copy twice', function () {
    // The commonest way a book ends up on two borrowers' records.
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $a, 'studentB' => $b] = circulationSetup();
    $copy = app(AddBookCopiesAction::class)->execute($title->id, 1)->first();

    app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $a->id);

    expect(fn () => app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $b->id))
        ->toThrow(ValidationException::class);

    expect(Loan::query()->count())->toBe(1);
});

it('insists on exactly one borrower', function () {
    // Two nullable columns are only as safe as the one action that writes them.
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    $copy = app(AddBookCopiesAction::class)->execute($title->id, 1)->first();

    expect(fn () => app(LendCopyAction::class)->execute($copy->id, $librarian->id))
        ->toThrow(ValidationException::class);

    expect(fn () => app(LendCopyAction::class)
        ->execute($copy->id, $librarian->id, studentId: $student->id, borrowerUserId: $librarian->id))
        ->toThrow(ValidationException::class);

    // Staff may borrow too.
    $loan = app(LendCopyAction::class)->execute($copy->id, $librarian->id, borrowerUserId: $librarian->id);
    expect($loan->student_id)->toBeNull()->and((int) $loan->borrower_user_id)->toBe((int) $librarian->id);
});

it('takes a book back by scanning the label, not by finding the loan', function () {
    // Somebody hands over a book and you scan it. Nobody at a return desk
    // knows which loan row it is.
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    $copy = app(AddBookCopiesAction::class)->execute($title->id, 1)->first();
    app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $student->id);

    $loan = app(ReturnCopyAction::class)->execute('AK000001', $librarian->id);

    expect($loan->status)->toBe(LoanStatus::Returned)
        ->and($loan->returned_on)->not->toBeNull()
        ->and($copy->refresh()->status)->toBe(CopyStatus::Available);

    // Taking back something that is not out is refused rather than silently
    // creating a phantom return.
    expect(fn () => app(ReturnCopyAction::class)->execute('AK000001', $librarian->id))
        ->toThrow(ValidationException::class);

    expect(fn () => app(ReturnCopyAction::class)->execute('NOPE', $librarian->id))
        ->toThrow(ValidationException::class);
});

it('closes the loan when a book is reported lost', function () {
    // A lost book must not sit open on a borrower's record forever.
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    $copy = app(AddBookCopiesAction::class)->execute($title->id, 1)->first();
    app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $student->id);

    $loan = app(ReturnCopyAction::class)->execute('AK000001', $librarian->id, lost: true);

    expect($loan->status)->toBe(LoanStatus::Lost)
        ->and($copy->refresh()->status)->toBe(CopyStatus::Lost)
        ->and(app(ListLoansAction::class)->forStudents([$student->id]))->toHaveCount(0);

    // And a lost copy is not lendable again by accident.
    expect(fn () => app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $student->id))
        ->toThrow(ValidationException::class);
});

it('answers "have you got it, and if not when is it back"', function () {
    // "None until Thursday" is a better answer than "none".
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    app(AddBookCopiesAction::class)->execute($title->id, 2);

    $row = app(ListCirculationAction::class)->execute('Fathuruveri')->first();
    expect($row['available'])->toBe(2)->and($row['on_loan'])->toBe(0)->and($row['soonest_back'])->toBeNull();

    foreach (BookCopy::query()->available()->get() as $copy) {
        app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $student->id);
    }

    $row = app(ListCirculationAction::class)->execute('Fathuruveri')->first();
    expect($row['available'])->toBe(0)
        ->and($row['on_loan'])->toBe(2)
        ->and($row['soonest_back'])->not->toBeNull();

    // And the second question: who has it.
    $copies = app(ListCirculationAction::class)->copies($title->id);
    expect($copies)->toHaveCount(2)->and($copies->first()['borrower'])->toBe('Hassan Ali');
});

it('issues a class its textbooks and reports who missed out', function () {
    // Partial success is the design. Forty textbooks are forty independent
    // facts, and one pupil who already holds a copy must not leave the other
    // thirty-nine unissued.
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $a, 'studentB' => $b] = circulationSetup();
    app(AddBookCopiesAction::class)->execute($title->id, 1);

    $third = makeStudent(['first_name' => 'Ibrahim', 'last_name' => 'Moosa']);

    $result = app(BulkIssueTextbooksAction::class)
        ->execute($title->id, [$a->id, $b->id, $third->id], $librarian->id);

    // One copy, three pupils: one issued, two named with a reason.
    expect($result['issued'])->toHaveCount(1)
        ->and($result['skipped'])->toHaveCount(2)
        ->and($result['skipped'][0]['reason'])->toBe('no copy left on the shelf');

    // Give the others a copy each, and re-running skips whoever already has one.
    app(AddBookCopiesAction::class)->execute($title->id, 2);
    $again = app(BulkIssueTextbooksAction::class)
        ->execute($title->id, [$a->id, $b->id, $third->id], $librarian->id);

    expect($again['issued'])->toHaveCount(2)
        ->and($again['skipped'])->toHaveCount(1)
        ->and($again['skipped'][0]['reason'])->toBe('already has a copy')
        ->and($again['skipped'][0]['student_id'])->toBe((int) $a->id);

    expect(fn () => app(BulkIssueTextbooksAction::class)->execute($title->id, [], $librarian->id))
        ->toThrow(ValidationException::class);
});

it('collects a class back in and names who still has one', function () {
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $a, 'studentB' => $b] = circulationSetup();
    app(AddBookCopiesAction::class)->execute($title->id, 2);
    app(BulkIssueTextbooksAction::class)->execute($title->id, [$a->id, $b->id], $librarian->id);

    // One pupil hands theirs in at the desk beforehand.
    app(ReturnCopyAction::class)->execute('AK000001', $librarian->id);

    $result = app(BulkReturnTextbooksAction::class)->execute($title->id, [$a->id, $b->id], $librarian->id);

    expect($result['returned'])->toHaveCount(1)
        ->and($result['outstanding'])->toBe([(int) $a->id]);

    expect(BookCopy::query()->available()->count())->toBe(2);
});

it('derives overdue from the due date rather than a nightly job', function () {
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    $copy = app(AddBookCopiesAction::class)->execute($title->id, 1)->first();
    app(LendCopyAction::class)->execute($copy->id, $librarian->id, studentId: $student->id);

    expect(app(ListLoansAction::class)->outstanding(overdueOnly: true))->toHaveCount(0);

    $this->travel(20)->days();

    $overdue = app(ListLoansAction::class)->outstanding(overdueOnly: true);
    expect($overdue)->toHaveCount(1)
        ->and($overdue->first()['overdue'])->toBeTrue()
        ->and($overdue->first()['days_overdue'])->toBe(6)
        ->and($overdue->first()['borrower'])->toBe('Hassan Ali');
});

it('encodes an accession number as a scannable barcode', function () {
    $svg = Code39::svg('AK000001');

    expect($svg)->toStartWith('<svg')
        ->toContain('</svg>')
        ->toContain('<rect')
        // The label is readable by a person as well as a scanner.
        ->toContain('aria-label="AK000001"');

    // The start/stop sentinel may not appear inside the payload, or a scanner
    // reads the code as truncated.
    expect(Code39::svg('AK*01'))->toContain('aria-label="AK01"');

    // Nothing blows up on an empty or junk value.
    expect(Code39::svg(''))->toStartWith('<svg');
});

it('walks the screens over http and shows a family only their own loans', function () {
    ['title' => $title, 'librarian' => $librarian, 'studentA' => $student] = circulationSetup();
    app(AddBookCopiesAction::class)->execute($title->id, 2);

    $guardian = User::factory()->create(['name' => 'Ali Hassan']);
    $guardianId = \Illuminate\Support\Facades\DB::table('parent_guardians')->insertGetId([
        'user_id' => $guardian->id, 'first_name' => 'Ali', 'last_name' => 'Hassan',
        'phone' => '7770003', 'email' => 'ali.hassan@example.test',
        'address' => 'Malé', 'relationship' => 'father',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \Illuminate\Support\Facades\DB::table('guardian_student')->insert([
        'guardian_id' => $guardianId, 'student_id' => $student->id,
        'relationship' => 'father', 'is_primary' => true, 'can_pickup' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.lend'), ['accession_number' => 'AK000001', 'student_id' => $student->id])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Circulation/Index')->has('titles', 1)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.titles.show', $title->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Circulation/Title')->has('copies', 2)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.labels', $title->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Circulation/Labels')->has('labels', 2)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.barcode', 'AK000001'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    // The family sees their child's loan, and nothing else.
    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->get(route('portal.loans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Loans')->has('loans', 1)->etc());

    // And the desk is not a family screen.
    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->get(route('circulation.index'))->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($guardian)
        ->post(route('circulation.return'), ['accession_number' => 'AK000001'])->assertForbidden();
});
