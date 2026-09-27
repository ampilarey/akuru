<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\HR\Actions\ListPublicInstructorProfilesAction;
use App\Domains\Website\Actions\ListResearchPostsAction;
use App\Domains\Website\Actions\PresentResearchPostAction;
use App\Domains\Website\Actions\SaveResearchPostAction;
use App\Domains\Website\Models\Post;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Research posts (W25). Inertia since C9 slice 8 (STATUS §5jj), with its
 * strings keyed for Dhivehi and Arabic. `role:super_admin` on the route
 * group. Validation is SaveResearchPostAction's, surfaced as field errors.
 */
class ResearchPostController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['year', 'instructor_id', 'q']);

        return Inertia::render('Website/Research', [
            'posts' => app(ListResearchPostsAction::class)->execute($filters, false)->all(),
            'years' => app(ListResearchPostsAction::class)->years(false),
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
            'filters' => ['year' => (string) ($filters['year'] ?? ''), 'instructor_id' => (string) ($filters['instructor_id'] ?? ''), 'q' => (string) ($filters['q'] ?? '')],
            't' => trans('admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListResearchPostsAction::class)->execute($request->only(['year', 'instructor_id', 'q']), false);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'slug', 'year', 'authors', 'published_at', 'is_published', 'pdf_url']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['id'],
                    $row['title'],
                    $row['slug'],
                    $row['year'],
                    $row['authors_label'],
                    $row['published_at'],
                    $row['is_published'] ? 'yes' : 'no',
                    $row['pdf']['url'] ?? '',
                ]);
            }
            fclose($out);
        }, 'research.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): Response
    {
        return Inertia::render('Website/ResearchForm', [
            'item' => null,
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
            't' => trans('admin'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pdf = $request->file('pdf');
        $row = app(SaveResearchPostAction::class)->execute(
            $request->all(),
            null,
            (int) $request->user()->id,
            $pdf instanceof UploadedFile ? $pdf : null,
        );

        return redirect()
            ->route('admin.research.edit', $row)
            ->with('success', trans('admin.research_saved'));
    }

    public function edit(Post $post): Response
    {
        $item = app(PresentResearchPostAction::class)->execute($post);
        abort_if($item === null, 404);

        return Inertia::render('Website/ResearchForm', [
            'item' => $item,
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
            't' => trans('admin'),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        abort_unless(app(PresentResearchPostAction::class)->execute($post) !== null, 404);

        $pdf = $request->file('pdf');
        app(SaveResearchPostAction::class)->execute(
            $request->all(),
            $post,
            (int) $request->user()->id,
            $pdf instanceof UploadedFile ? $pdf : null,
        );

        return redirect()
            ->route('admin.research.edit', $post)
            ->with('success', trans('admin.research_updated'));
    }
}
