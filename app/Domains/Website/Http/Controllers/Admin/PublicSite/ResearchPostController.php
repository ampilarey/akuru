<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\HR\Actions\ListPublicInstructorProfilesAction;
use App\Domains\Website\Actions\ListResearchPostsAction;
use App\Domains\Website\Actions\PresentResearchPostAction;
use App\Domains\Website\Actions\SaveResearchPostAction;
use App\Domains\Website\Models\Post;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchPostController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['year', 'instructor_id', 'q']);

        return view('admin.public-site.research.index', [
            'posts' => app(ListResearchPostsAction::class)->execute($filters, false),
            'years' => app(ListResearchPostsAction::class)->years(false),
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListResearchPostsAction::class)->execute($request->only(['year', 'instructor_id', 'q']), false);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'title', 'slug', 'year', 'authors', 'published_at', 'is_published', 'pdf_url']);
            foreach ($rows as $row) {
                fputcsv($out, [
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

    public function create()
    {
        return view('admin.public-site.research.form', [
            'item' => null,
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
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
            ->with('success', 'Research post saved.');
    }

    public function edit(Post $post)
    {
        $item = app(PresentResearchPostAction::class)->execute($post);
        abort_if($item === null, 404);

        return view('admin.public-site.research.form', [
            'item' => $item,
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
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
            ->with('success', 'Research post updated.');
    }
}
