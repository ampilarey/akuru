<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\DecideBlogPageAction;
use App\Domains\Library\Actions\Blog\ListBlogPageChangesAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BLOG_PLAN BL3a (D14): the Library office's list of blog page changes
 * waiting to be checked, and its CSV. Behind the Library office's gate
 * (`role:super_admin`, `can:library.manage`).
 */
class AdminBlogPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Library/BlogPageChanges', [
            'changes' => app(ListBlogPageChangesAction::class)->execute(),
            't' => Phrases::once('blog'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = app(ListBlogPageChangesAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'pen_name', 'student', 'sent_at', 'theme_now', 'theme_next', 'accent_next', 'font_next', 'tagline_next', 'about_next', 'languages_next', 'sections_next']);
            foreach ($rows as $r) {
                $next = $r['next'];
                Csv::put($out, [
                    $r['id'], $r['display_name'], $r['student'] ? 'yes' : 'no', $r['submitted_at'], $r['live']['theme_name'], $next['theme_name'],
                    $next['accent'] ?? '', $next['font'] ?? '', $next['tagline'] ?? '', $next['about'] ?? '', implode(' ', $next['languages']),
                    implode(' ', array_map(fn (array $s) => $s['key'].($s['on'] ? '' : ':off'), $next['sections'])),
                ]);
            }
            fclose($out);
        }, 'blog-page-changes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function decide(Request $request, int $profile): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'refuse'])],
            'note' => 'nullable|string|max:500',
        ]);

        app(DecideBlogPageAction::class)->execute($profile, (int) $request->user()->id, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', __($data['decision'] === 'approve' ? 'blog.flash_page_approved' : 'blog.flash_page_refused'));
    }
}
