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
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Circulation — physical lending. Thin (rule 5).
 *
 * **Not the L-track Library.** The plan asks for the names to stay apart in
 * code and in nav so nobody confuses a paper book on a shelf with a digital
 * title in the reader, and they do.
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
        ]);
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
        ]);

        $save->execute($data);

        return back()->with('success', 'Title added.');
    }

    public function addCopies(Request $request, BookTitle $title, AddBookCopiesAction $add): RedirectResponse
    {
        $data = $request->validate([
            'how_many' => ['required', 'integer', 'min:1', 'max:200'],
            'shelf' => ['nullable', 'string', 'max:40'],
        ]);

        $made = $add->execute((int) $title->id, (int) $data['how_many'], $data['shelf'] ?? null);

        return back()->with('success', $made->count().' copies added. Print their labels before they go on the shelf.');
    }

    public function lend(Request $request, LendCopyAction $lend): RedirectResponse
    {
        $data = $request->validate([
            'accession_number' => ['required', 'string', 'max:32'],
            'student_id' => ['nullable', 'integer', 'min:1'],
            'borrower_user_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $copy = BookCopy::query()->where('accession_number', trim($data['accession_number']))->first();

        abort_if($copy === null, 422, 'No copy with that accession number.');

        $lend->execute(
            (int) $copy->id,
            (int) $request->user()->id,
            studentId: $data['student_id'] ?? null,
            borrowerUserId: $data['borrower_user_id'] ?? null,
            note: $data['note'] ?? null,
        );

        return back()->with('success', 'Lent.');
    }

    public function return(Request $request, ReturnCopyAction $return): RedirectResponse
    {
        $data = $request->validate([
            'accession_number' => ['required', 'string', 'max:32'],
            'lost' => ['nullable', 'boolean'],
        ]);

        $return->execute($data['accession_number'], (int) $request->user()->id, (bool) ($data['lost'] ?? false));

        return back()->with('success', 'Taken back in.');
    }

    public function bulkIssue(Request $request, BookTitle $title, BulkIssueTextbooksAction $issue): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'min:1'],
        ]);

        $result = $issue->execute((int) $title->id, $data['student_ids'], (int) $request->user()->id);

        // The short list is the point: a librarian acts on who did not get one.
        return back()->with('success', sprintf(
            '%d issued, %d not. %s',
            count($result['issued']),
            count($result['skipped']),
            count($result['skipped']) === 0 ? 'Everybody has a copy.' : 'See who is outstanding below.',
        ))->with('bulk_result', $result);
    }

    public function bulkReturn(Request $request, BookTitle $title, BulkReturnTextbooksAction $return): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'min:1'],
        ]);

        $result = $return->execute((int) $title->id, $data['student_ids'], (int) $request->user()->id);

        return back()->with('success', sprintf(
            '%d taken back, %d still outstanding.',
            count($result['returned']),
            count($result['outstanding']),
        ))->with('bulk_result', $result);
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
}
