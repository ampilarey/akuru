<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ListLibraryCategoriesAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\PresentLibraryItemAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L1 public surface (LIBRARY_PLAN §8): listing with basic search and
 * filters, detail page with the free-reading gate. Blade, like the rest of
 * the public site zone (the W2.5 research precedent).
 */
class PublicLibraryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'content_type', 'category', 'tag']);

        return view('public.library.index', [
            'items' => app(ListLibraryItemsAction::class)->execute($filters),
            'categories' => app(ListLibraryCategoriesAction::class)->execute(withCounts: true),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListLibraryItemsAction::class)->execute(
            $request->only(['q', 'content_type', 'category', 'tag'])
        );

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'title', 'type', 'access', 'category', 'authors', 'published_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['title'],
                    $row['content_type'],
                    $row['access_type'],
                    $row['category']['name'] ?? '',
                    implode('; ', $row['authors']),
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
}
