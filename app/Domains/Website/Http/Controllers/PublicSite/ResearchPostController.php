<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\HR\Actions\ListPublicInstructorProfilesAction;
use App\Domains\Website\Actions\ListResearchPostsAction;
use App\Domains\Website\Actions\PresentResearchPostAction;
use App\Domains\Website\Models\Post;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchPostController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['year', 'instructor_id']);

        return view('public.research.index', [
            'posts' => app(ListResearchPostsAction::class)->execute($filters, true),
            'years' => app(ListResearchPostsAction::class)->years(true),
            'instructors' => app(ListPublicInstructorProfilesAction::class)->execute(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListResearchPostsAction::class)->execute($request->only(['year', 'instructor_id']), true);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'title', 'slug', 'year', 'authors', 'published_at', 'pdf_url']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['title'],
                    $row['slug'],
                    $row['year'],
                    $row['authors_label'],
                    $row['published_at'],
                    $row['pdf']['url'] ?? '',
                ]);
            }
            fclose($out);
        }, 'research.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Post $post)
    {
        $item = app(PresentResearchPostAction::class)->execute($post);
        if ($item === null || ! $item['is_published']) {
            abort(404);
        }

        return view('public.research.show', ['item' => $item]);
    }
}
