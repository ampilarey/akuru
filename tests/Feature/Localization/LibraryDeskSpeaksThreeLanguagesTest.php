<?php

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Circulation\Actions\AddBookCopiesAction;
use App\Domains\Circulation\Actions\SaveBookTitleAction;
use App\Domains\Circulation\Enums\CopyStatus;
use App\Domains\Circulation\Enums\LoanStatus;
use App\Domains\Circulation\Models\Loan;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * The library desk in Dhivehi and Arabic (BACKLOG C21, slice LD1, STATUS §5qt).
 *
 * The desk, a title with its copies, the borrower cards and the label sheet
 * read no phrase book: every word was English, a copy's state was the
 * server's English label, and so was everything the server said. Three
 * things the desk could not do were found on the way: a scanned borrower
 * card was refused — its barcode is the pupil's student number, and the box
 * took the row's id — and said nowhere; a label nobody has opened a bare 422
 * page; and a class issue's result, who was not issued a book and why, was
 * never passed to the page.
 */
uses(RefreshDatabase::class);

/** The desk's screens; each reads the `circulation` book as `t`. */
function libraryDeskScreens(): array
{
    return ['Circulation/Index', 'Circulation/Title', 'Circulation/BorrowerCards', 'Circulation/Labels'];
}

/** Where the server writes what those screens say. */
function libraryDeskServerFiles(): array
{
    return [
        'app/Domains/Circulation/Http/Controllers/CirculationController.php',
        'app/Domains/Circulation/Actions/AddBookCopiesAction.php',
        'app/Domains/Circulation/Actions/BulkIssueTextbooksAction.php',
        'app/Domains/Circulation/Actions/LendCopyAction.php',
        'app/Domains/Circulation/Actions/ReturnCopyAction.php',
        'app/Domains/Circulation/Actions/SaveBookTitleAction.php',
        'app/Domains/Circulation/Actions/ListLoansAction.php',
        'app/Domains/Circulation/Actions/ListCirculationAction.php',
        'app/Domains/Circulation/Enums/CopyStatus.php',
        'app/Domains/Circulation/Enums/LoanStatus.php',
    ];
}

function circulationBookFor(string $locale): array
{
    return require base_path("resources/lang/{$locale}/circulation.php");
}

/** A librarian, a title with two copies, and two pupils with student numbers. */
function libraryDeskSetup(): array
{
    makeYear(['name' => 'Desk year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);
    Role::findOrCreate('admin', 'web');
    $librarian = User::factory()->create(['name' => 'Desk Librarian']);
    $librarian->assignRole('admin');
    $title = app(SaveBookTitleAction::class)->execute(['title' => 'Fathuruveri', 'author' => 'Anonymous', 'loan_days' => 14]);
    app(AddBookCopiesAction::class)->execute((int) $title->id, 2);

    return [
        'librarian' => $librarian->fresh(),
        'title' => $title,
        'hassan' => makeStudent(['first_name' => 'Hassan', 'last_name' => 'Ali', 'student_id' => 'LD1-0001']),
        'aishath' => makeStudent(['first_name' => 'Aishath', 'last_name' => 'Ibrahim', 'student_id' => 'LD1-0002']),
    ];
}

it('keys every string on the library desk in three languages', function () {
    [$en, $dv, $ar] = [circulationBookFor('en'), circulationBookFor('dv'), circulationBookFor('ar')];

    foreach (libraryDeskScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: circulation.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: circulation.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: circulation.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: circulation.{$key} says something else in English than the screen");
            if (trim($en[$key], ' ,') !== '') {
                expect($dv[$key])->not->toBe($en[$key], "circulation.{$key} is English in Dhivehi")
                    ->and($ar[$key])->not->toBe($en[$key], "circulation.{$key} is English in Arabic");
            }
        }

        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }
});

it('names every field the desk posts, so a refusal by Laravel’s own rules reads whole in Dhivehi and Arabic', function () {
    $source = file_get_contents(base_path('app/Domains/Circulation/Http/Controllers/CirculationController.php'));
    preg_match_all("/'([a-z_]+(?:\\.\\*)?)' => \\[(?=[^\\]]*'(?:required|nullable|integer|string|array|boolean)')/", $source, $found);
    expect($found[1])->not->toBeEmpty();

    foreach (array_unique($found[1]) as $field) {
        expect(preg_match('/\''.preg_quote($field, '/').'\' => __\(\'circulation\.attr_/', $source))->toBe(1, "{$field} is not named from the book");
    }
});

it('names every state of a copy and a loan, in all three languages', function () {
    $keys = [
        ...array_map(fn (CopyStatus $status) => 'circulation.copy_status_'.$status->value, CopyStatus::cases()),
        ...array_map(fn (LoanStatus $status) => 'circulation.loan_status_'.$status->value, LoanStatus::cases()),
    ];
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }

    // The English the server said before is the English it says now.
    expect(CopyStatus::Available->label())->toBe('On the shelf')
        ->and(LoanStatus::Returned->label())->toBe('Returned');
    app()->setLocale('dv');
    expect(CopyStatus::OnLoan->label())->toBe(trans('circulation.copy_status_on_loan', [], 'dv'));
});

it('leaves no English in what the server says on the desk, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (libraryDeskServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(libraryDeskServerFiles());
    expect($keys)->toContain('circulation.flash_lent', 'circulation.error_copy_not_found', 'circulation.error_no_such_pupil',
        'circulation.reason_has_copy', 'circulation.attr_accession_number', 'circulation.unknown_pupil');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('lends to the pupil a scanned borrower card names, and refuses an unknown label or card under the form, in Dhivehi', function () {
    ['librarian' => $librarian, 'hassan' => $hassan] = libraryDeskSetup();
    $dv = circulationBookFor('dv');
    app()->setLocale('dv');

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Circulation/Index')->where('t.desk_title', $dv['desk_title']));

    // A label nobody has was a bare 422 page; it is refused under the form.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->from(route('circulation.index'))
        ->post(route('circulation.lend'), ['accession_number' => 'AK999999', 'student_number' => 'LD1-0001'])
        ->assertRedirect(route('circulation.index'))
        ->assertSessionHasErrors(['accession_number' => $dv['error_copy_not_found']]);

    // A card nobody has is refused by name, in Dhivehi; nothing is lent.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.lend'), ['accession_number' => 'AK000001', 'student_number' => 'LD1-9999'])
        ->assertSessionHasErrors(['student_number' => $dv['error_no_such_pupil']]);
    expect(Loan::query()->count())->toBe(0);

    // A blank label is refused by its Dhivehi name.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.lend'), ['accession_number' => '', 'student_number' => 'LD1-0001']);
    expect(session('errors')->first('accession_number'))->toContain($dv['attr_accession_number'])->not->toMatch('/[A-Za-z]{3,}/');

    // The card's number lends to its pupil, said in Dhivehi with the day it is due.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.lend'), ['accession_number' => 'AK000001', 'student_number' => 'LD1-0001'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('circulation.flash_lent', ['date' => now()->addDays(14)->toDateString()], 'dv'));
    expect(Loan::query()->out()->value('student_id'))->toBe($hassan->id);

    // Lending it again is refused in Dhivehi; taking it back is said in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.lend'), ['accession_number' => 'AK000001', 'student_number' => 'LD1-0002'])
        ->assertSessionHasErrors(['copy' => $dv['error_copy_out']]);
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.return'), ['accession_number' => 'AK000001'])
        ->assertSessionHas('success', $dv['flash_returned']);
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.return'), ['accession_number' => 'AK000001'])
        ->assertSessionHasErrors(['copy' => $dv['error_not_on_loan']]);
});

it('shows a class issue’s result by the pupils’ names, why each was skipped in Dhivehi', function () {
    ['librarian' => $librarian, 'title' => $title, 'hassan' => $hassan, 'aishath' => $aishath] = libraryDeskSetup();
    $third = makeStudent(['first_name' => 'Mariyam', 'last_name' => 'Hassan', 'student_id' => 'LD1-0003']);
    $dv = circulationBookFor('dv');
    app()->setLocale('dv');

    // Two copies for three pupils: one is skipped, by name, with the reason in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->from(route('circulation.titles.show', $title->id))
        ->post(route('circulation.bulk.issue', $title->id), ['student_ids' => [$hassan->id, $aishath->id, $third->id]])
        ->assertSessionHas('success', __('circulation.flash_issued_some', ['issued' => 2, 'skipped' => 1], 'dv'));

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.titles.show', $title->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Circulation/Title')
            ->where('t.issue_heading', $dv['issue_heading'])
            ->where('bulk_result.issued', 2)
            ->where('bulk_result.skipped.0.name', 'Mariyam Hassan')
            ->where('bulk_result.skipped.0.reason', $dv['reason_none_left'])
            ->where('copies.0.status_label', $dv['copy_status_on_loan']));

    // Choosing nobody is refused by the field's Dhivehi name.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.bulk.issue', $title->id), ['student_ids' => []]);
    expect(session('errors')->first('student_ids'))->toContain($dv['attr_student_ids']);

    // Collecting back names who had nothing to hand in.
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->post(route('circulation.bulk.return', $title->id), ['student_ids' => [$hassan->id, $third->id]])
        ->assertSessionHas('success', __('circulation.flash_collected', ['returned' => 1, 'outstanding' => 1], 'dv'));
    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.titles.show', $title->id))
        ->assertInertia(fn (Assert $page) => $page->where('bulk_result.returned', 1)->where('bulk_result.outstanding.0', 'Mariyam Hassan'));
});

it('serves the borrower cards and the label sheet in Dhivehi', function () {
    ['librarian' => $librarian, 'title' => $title] = libraryDeskSetup();
    $dv = circulationBookFor('dv');
    app()->setLocale('dv');

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.cards', ['q' => 'Hassan']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Circulation/BorrowerCards')
            ->where('t.cards_title', $dv['cards_title'])->where('cards.0.student_number', 'LD1-0001'));

    $this->withoutLocalizationMiddleware()->actingAs($librarian)
        ->get(route('circulation.labels', $title->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Circulation/Labels')->where('t.labels_title', $dv['labels_title'])->has('labels', 2));
});
