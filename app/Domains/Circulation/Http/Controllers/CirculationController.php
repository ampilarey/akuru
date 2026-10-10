<?php

namespace App\Domains\Circulation\Http\Controllers;

use App\Domains\Circulation\Actions\AddBookCopiesAction;
use App\Domains\Circulation\Actions\BulkIssueTextbooksAction;
use App\Domains\Circulation\Actions\BulkReturnTextbooksAction;
use App\Domains\Circulation\Actions\LendCopyAction;
use App\Domains\Circulation\Actions\ListCirculationAction;
use App\Domains\Circulation\Actions\ListLoansAction;
use App\Domains\Circulation\Actions\ReturnCopyAction;
use App\Domains\Circulation\Actions\SaveBookTitleAction;
use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\BookTitle;
use App\Domains\Circulation\Support\Code39;
use App\Domains\People\Actions\FindStudentByNumberAction;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Circulation — physical lending. Thin (rule 5).
 *
 * **Not the L-track Library.** The plan asks for the names to stay apart in
 * code and in nav so nobody confuses a paper book on a shelf with a digital
 * title in the reader, and they do.
 *
 * What it says, and the names of the fields in Laravel's own refusals, are
 * the `circulation` book's, in the page's language (slice LD1, STATUS §5qt).
 */
class CirculationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('Circulation/Index', [
            'q' => $query,
            'titles' => app(ListCirculationAction::class)->execute($query),
            'overdue' => app(ListLoansAction::class)->outstanding(overdueOnly: true),
            't' => Phrases::once('circulation'),
        ]);
    }

    /**
     * CLAUDE.md: *"every listing gets CSV export."*
     *
     * The stock take. Every title with its copy counts, so a librarian can
     * reconcile the shelf against the system once a year without reading 50
     * rows off a screen — and `soonest_back` comes along, because a title with
     * nothing available is a different problem depending on whether it is back
     * on Thursday or has been out since March.
     *
     * Not limited to the 50 rows `index()` shows: an export of the first page
     * of a stock list is not a stock list.
     */
    public function export(Request $request, ListCirculationAction $list): StreamedResponse
    {
        $titles = $list->execute(trim((string) $request->query('q', '')), limit: 5000);

        return response()->streamDownload(function () use ($titles): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'title', 'author', 'isbn', 'classification', 'loan_days', 'total_copies', 'available', 'on_loan', 'soonest_back']);

            foreach ($titles as $row) {
                Csv::put($handle, [
                    $row['id'],
                    $row['title'],
                    $row['author'],
                    $row['isbn'],
                    $row['classification'],
                    $row['loan_days'],
                    $row['total'],
                    $row['available'],
                    $row['on_loan'],
                    $row['soonest_back'],
                ]);
            }

            fclose($handle);
        }, 'circulation.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, BookTitle $title): Response
    {
        return Inertia::render('Circulation/Title', [
            'title' => [
                'id' => (int) $title->id,
                'title' => $title->title,
                'author' => $title->author,
                'isbn' => $title->isbn,
                'classification' => $title->classification,
                'loan_days' => (int) $title->loan_days,
                'notes' => $title->notes,
            ],
            'copies' => app(ListCirculationAction::class)->copies((int) $title->id),
            'q' => trim((string) $request->query('q', '')),
            'matches' => $request->query('q')
                ? app(SearchRosterCandidatesAction::class)->execute((string) $request->query('q'), 12)
                : [],
            'bulk_result' => $this->named($request->session()->get('bulk_result')),
            't' => Phrases::once('circulation'),
        ]);
    }

    public function storeTitle(Request $request, SaveBookTitleAction $save): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'author' => ['nullable', 'string', 'max:191'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'classification' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', 'string', 'max:8'],
            'loan_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], $this->fieldNames());

        $save->execute($data);

        return back()->with('success', __('circulation.flash_title_added'));
    }

    public function addCopies(Request $request, BookTitle $title, AddBookCopiesAction $add): RedirectResponse
    {
        $data = $request->validate([
            'how_many' => ['required', 'integer', 'min:1', 'max:200'],
            'shelf' => ['nullable', 'string', 'max:40'],
        ], [], $this->fieldNames());

        $made = $add->execute((int) $title->id, (int) $data['how_many'], $data['shelf'] ?? null);

        return back()->with('success', $made->count() === 1
            ? __('circulation.flash_copies_added_one')
            : __('circulation.flash_copies_added', ['count' => $made->count()]));
    }

    public function lend(Request $request, LendCopyAction $lend): RedirectResponse
    {
        $data = $request->validate([
            'accession_number' => ['required', 'string', 'max:32'],
            'student_number' => ['nullable', 'string', 'max:32'],
            'student_id' => ['nullable', 'integer', 'min:1'],
            'borrower_user_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:191'],
        ], [], $this->fieldNames());

        $copy = BookCopy::query()->where('accession_number', trim($data['accession_number']))->first();

        // A label nobody has: refused under the form, where it was a bare 422
        // page (slice LD1).
        if ($copy === null) {
            throw ValidationException::withMessages(['accession_number' => __('circulation.error_copy_not_found')]);
        }

        // A borrower card's barcode is the pupil's student number, not the
        // row's id: a scanned card was refused as not a number, and the form
        // said nothing (slice LD1).
        $studentId = $data['student_id'] ?? null;
        if (filled($data['student_number'] ?? null)) {
            $studentId = app(FindStudentByNumberAction::class)->execute((string) $data['student_number']);

            if ($studentId === null) {
                throw ValidationException::withMessages(['student_number' => __('circulation.error_no_such_pupil')]);
            }
        }

        $loan = $lend->execute(
            (int) $copy->id,
            (int) $request->user()->id,
            studentId: $studentId,
            borrowerUserId: $data['borrower_user_id'] ?? null,
            note: $data['note'] ?? null,
        );

        return back()->with('success', __('circulation.flash_lent', ['date' => $loan->due_on?->toDateString()]));
    }

    public function return(Request $request, ReturnCopyAction $return): RedirectResponse
    {
        $data = $request->validate([
            'accession_number' => ['required', 'string', 'max:32'],
            'lost' => ['nullable', 'boolean'],
        ], [], $this->fieldNames());

        $return->execute($data['accession_number'], (int) $request->user()->id, (bool) ($data['lost'] ?? false));

        return back()->with('success', __('circulation.flash_returned'));
    }

    public function bulkIssue(Request $request, BookTitle $title, BulkIssueTextbooksAction $issue): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'min:1'],
        ], [], $this->fieldNames());

        $result = $issue->execute((int) $title->id, $data['student_ids'], (int) $request->user()->id);
        $counts = ['issued' => count($result['issued']), 'skipped' => count($result['skipped'])];

        // The short list is the point: a librarian acts on who did not get one.
        return back()->with('success', $counts['skipped'] === 0
            ? __('circulation.flash_issued_all', $counts)
            : __('circulation.flash_issued_some', $counts))->with('bulk_result', $result);
    }

    public function bulkReturn(Request $request, BookTitle $title, BulkReturnTextbooksAction $return): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'min:1'],
        ], [], $this->fieldNames());

        $result = $return->execute((int) $title->id, $data['student_ids'], (int) $request->user()->id);

        return back()->with('success', __('circulation.flash_collected', [
            'returned' => count($result['returned']),
            'outstanding' => count($result['outstanding']),
        ]))->with('bulk_result', $result);
    }

    /** Printable label sheet for a title's copies — the barcode goes on the book. */
    public function labels(BookTitle $title): Response
    {
        $copies = app(ListCirculationAction::class)->copies((int) $title->id);

        return Inertia::render('Circulation/Labels', [
            'title' => ['id' => (int) $title->id, 'title' => $title->title, 'author' => $title->author],
            'labels' => $copies->map(fn (array $copy): array => [
                'accession_number' => $copy['accession_number'],
                'shelf' => $copy['shelf'],
                'barcode' => Code39::svg((string) $copy['accession_number'], height: 36, narrow: 2),
            ])->values(),
            't' => Phrases::once('circulation'),
        ]);
    }

    /** A borrower card: the pupil's number as a scannable barcode. */
    public function borrowerCard(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $matches = $query === '' ? [] : app(SearchRosterCandidatesAction::class)->execute($query, 12);

        return Inertia::render('Circulation/BorrowerCards', [
            'q' => $query,
            'cards' => array_map(fn (array $child): array => [
                'id' => $child['id'],
                'name' => $child['name'],
                'student_number' => $child['student_number'],
                'current_class' => $child['current_class'] ?? null,
                'barcode' => $child['student_number']
                    ? Code39::svg((string) $child['student_number'], height: 36, narrow: 2)
                    : null,
            ], $matches),
            't' => Phrases::once('circulation'),
        ]);
    }

    /** One barcode as a standalone SVG, for anything that wants an <img>. */
    public function barcode(string $value): HttpResponse
    {
        return response(Code39::svg($value), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * A class issue's or collection's result, its pupils named. The server
     * kept it in the session and nothing passed it to the page, so who was
     * not issued a book, and why, was never shown (slice LD1); and it named a
     * pupil by the row's id.
     *
     * @param  array<string, mixed>|null  $result
     * @return array{issued: ?int, returned: ?int, skipped: list<array{student_id: int, name: string, reason: string}>, outstanding: list<string>}|null
     */
    private function named(?array $result): ?array
    {
        if ($result === null) {
            return null;
        }

        $ids = [
            ...array_column($result['skipped'] ?? [], 'student_id'),
            ...($result['outstanding'] ?? []),
        ];
        $names = app(ListStudentsByIdsAction::class)->execute(array_map('intval', $ids))->pluck('name', 'id');
        $name = fn (int $id): string => (string) ($names->get($id) ?? __('circulation.unknown_pupil'));

        return [
            'issued' => isset($result['issued']) ? count($result['issued']) : null,
            'returned' => isset($result['returned']) ? count($result['returned']) : null,
            'skipped' => array_map(fn (array $row): array => [
                'student_id' => (int) $row['student_id'],
                'name' => $name((int) $row['student_id']),
                'reason' => (string) $row['reason'],
            ], $result['skipped'] ?? []),
            'outstanding' => array_map(fn ($id): string => $name((int) $id), $result['outstanding'] ?? []),
        ];
    }

    /**
     * The fields the desk posts, named for Laravel's own refusals in the
     * page's language: a blank accession number read *accession number
     * ބޭނުންވޭ*.
     *
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return [
            'title' => __('circulation.attr_title'),
            'author' => __('circulation.attr_author'),
            'isbn' => __('circulation.attr_isbn'),
            'classification' => __('circulation.attr_classification'),
            'language' => __('circulation.attr_language'),
            'loan_days' => __('circulation.attr_loan_days'),
            'notes' => __('circulation.attr_notes'),
            'how_many' => __('circulation.attr_how_many'),
            'shelf' => __('circulation.attr_shelf'),
            'accession_number' => __('circulation.attr_accession_number'),
            'student_number' => __('circulation.attr_student_number'),
            'student_id' => __('circulation.attr_student_id'),
            'borrower_user_id' => __('circulation.attr_borrower_user_id'),
            'note' => __('circulation.attr_note'),
            'lost' => __('circulation.attr_lost'),
            'student_ids' => __('circulation.attr_student_ids'),
            'student_ids.*' => __('circulation.attr_student_id'),
        ];
    }
}
