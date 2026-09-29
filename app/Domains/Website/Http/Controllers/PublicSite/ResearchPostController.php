<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Library\Actions\ImportWebsiteResearchAction;
use App\Domains\Website\Actions\ListResearchPostsForImportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * RESEARCH_ARTICLES_PLAN R2: research lives in the Digital Library now. The
 * old website addresses stay, so links in the wild still land somewhere —
 * each one a permanent redirect to where the paper is now. The route names
 * stay too, so nothing that builds a URL to them breaks.
 */
class ResearchPostController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('public.library.index', $this->filters($request), 301);
    }

    public function export(Request $request): RedirectResponse
    {
        return redirect()->route('public.library.export', $this->filters($request), 301);
    }

    /** An old paper's address: its imported item's page, or nothing. */
    public function show(string $slug): RedirectResponse
    {
        $postId = app(ListResearchPostsForImportAction::class)->idForSlug($slug);
        $librarySlug = $postId !== null ? app(ImportWebsiteResearchAction::class)->slugForPost($postId) : null;
        abort_if($librarySlug === null, 404);

        return redirect()->route('public.library.show', $librarySlug, 301);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $year = $request->query('year');

        return ['content_type' => 'research'] + (is_numeric($year) ? ['year' => (string) (int) $year] : []);
    }
}
