<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Commerce\Actions\ListPromotionCampaignsAction;
use App\Domains\Library\Actions\DownloadLibraryItemAction;
use App\Domains\Library\Actions\ListLibraryCategoriesAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\ListMyLibraryAction;
use App\Domains\Library\Actions\PresentLibraryItemAction;
use App\Domains\Library\Actions\PresentWriterPublicProfileAction;
use App\Domains\Library\Actions\RecordLibrarySearchAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L1 public surface (LIBRARY_PLAN §8): listing with basic search and
 * filters, detail page with the free-reading gate. Blade, like the rest of
 * the public site zone (the W2.5 research precedent).
 */
class PublicLibraryController extends Controller
{
    /** The query-string keys the shelf understands (§8.2/§8.3). */
    private const FILTERS = ['q', 'content_type', 'category', 'tag', 'author', 'access', 'language', 'price_min', 'price_max', 'difficulty', 'reading', 'peer_reviewed', 'open_access', 'discounted', 'campaign', 'year', 'sort'];

    public function index(Request $request)
    {
        $filters = array_filter($request->only(self::FILTERS), fn ($value) => $value !== null && $value !== '');
        $browsing = array_diff_key($filters, ['sort' => 1]) === [];
        $items = app(ListLibraryItemsAction::class)->execute($filters);
        // B14 (§29): what was looked for, and whether it was found.
        if (isset($filters['q'])) {
            app(RecordLibrarySearchAction::class)->execute((string) $filters['q'], count($items), $request->user()?->id);
        }

        return view('public.library.index', [
            'items' => $items,
            'categories' => app(ListLibraryCategoriesAction::class)->execute(withCounts: true),
            'filters' => $filters,
            'sorts' => ListLibraryItemsAction::SORTS,
            'difficulties' => ListLibraryItemsAction::DIFFICULTIES,
            'reading_bands' => array_keys(ListLibraryItemsAction::READING_BANDS),
            'languages' => ['en' => 'English', 'dv' => 'Dhivehi', 'ar' => 'Arabic'],
            // R1 (F12): the years research was published in, for the research shelf's year filter.
            'years' => ($filters['content_type'] ?? null) === 'research' ? app(ListLibraryItemsAction::class)->publishedYears('research') : [],
            // §8.1: the office's picks and the reader's own half-read books,
            // on the front of the shelf and nowhere else — a filtered list
            // is an answer to a question, not a shop window.
            'featured' => $browsing ? app(ListLibraryItemsAction::class)->execute(['featured' => true]) : [],
            // B4 (§8.1): the offers running now, a strip on the front of the shelf.
            'promotions' => $browsing ? app(ListPromotionCampaignsAction::class)->active() : [],
            'continue_reading' => $browsing && $request->user()
                ? array_slice(array_values(array_filter(app(ListMyLibraryAction::class)->execute((int) $request->user()->id)['continue'], fn ($row) => ! $row['completed'])), 0, 3)
                : [],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListLibraryItemsAction::class)->execute(
            $request->only(self::FILTERS)
        );

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'type', 'access', 'category', 'authors', 'difficulty', 'reading_time', 'published_at']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['id'],
                    $row['title'],
                    $row['content_type'],
                    $row['access_type'],
                    $row['category']['name'] ?? '',
                    implode('; ', $row['authors']),
                    $row['difficulty'] ?? '',
                    $row['reading_time'] ?? '',
                    $row['published_at'],
                ]);
            }
            fclose($out);
        }, 'library.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, string $slug)
    {
        $item = app(PresentLibraryItemAction::class)->execute(
            $slug,
            $request->user()?->id,
        );
        if ($item === null) {
            abort(404);
        }

        return view('public.library.show', ['item' => $item]);
    }

    /**
     * R1 (D1): the PDF itself, where the author chose "download" or "both".
     * The action makes the access decision; this only turns it into a reply.
     */
    public function download(Request $request, string $slug): HttpResponse|RedirectResponse
    {
        $result = app(DownloadLibraryItemAction::class)->execute(
            $slug,
            $request->user()?->id,
            $request->hasSession() ? $request->session()->getId() : null,
            $request->ip(),
            $request->userAgent(),
        );

        return match ($result['status']) {
            'ok' => response($result['file']['contents'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]),
            'login' => redirect()->guest(route('login')),
            'locked' => redirect()->route('public.library.show', $slug),
            default => abort(404),
        };
    }

    /** B4 (§8.5, §18): the offers running now, each with what it covers. */
    public function promotions()
    {
        $campaigns = app(ListPromotionCampaignsAction::class)->active();
        $items = app(ListLibraryItemsAction::class);

        return view('public.library.promotions', [
            'promotions' => array_map(fn (array $campaign) => $campaign + [
                'items' => array_slice($items->execute(['campaign' => $campaign['slug']]), 0, 8),
            ], $campaigns),
        ]);
    }

    /** L8 (§8.7): an author and everything of theirs that is published. */
    public function author(string $slug)
    {
        $author = app(PresentWriterPublicProfileAction::class)->execute($slug);
        if ($author === null) {
            abort(404);
        }

        return view('public.library.author', ['author' => $author]);
    }
}
