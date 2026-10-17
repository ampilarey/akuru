<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\FeaturePostAction;
use App\Domains\Library\Actions\Blog\ListBlogInsightsAction;
use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BLOG_PLAN BL7: the blogs' insights on the Library office, *Featured this
 * month* with its reward, and a CSV. Behind the Library office's gate
 * (`role:super_admin`, `can:library.manage`).
 */
class AdminBlogInsightsController extends Controller
{
    public function index(): Response
    {
        $settings = app(ResolveLibrarySettingAction::class);

        return Inertia::render('Library/BlogInsights', [
            ...app(ListBlogInsightsAction::class)->execute(),
            'reward' => ['enabled' => (bool) $settings->execute('reward_enabled'), 'cap' => (int) $settings->execute('reward_cap')],
            't' => Phrases::once('blog'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = app(ListBlogInsightsAction::class)->execute()['recent'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'blog', 'student', 'published_at', 'readers', 'featured_this_month', 'reward']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['id'], $r['title'], $r['blog'], $r['student'] ? 'yes' : 'no', $r['published_at'], $r['reads'], $r['featured'] ? 'yes' : 'no', $r['reward'] !== null ? number_format($r['reward'], 2, '.', '') : '']);
            }
            fclose($out);
        }, 'blog-insights.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function feature(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate(['feature' => 'required|boolean', 'reward' => 'nullable|numeric|min:0|max:100000']);
        app(FeaturePostAction::class)->execute($item, (int) $request->user()->id, (bool) $data['feature'], isset($data['reward']) ? (float) $data['reward'] : null);

        return back()->with('success', __($data['feature'] ? 'blog.flash_featured' : 'blog.flash_unfeatured'));
    }
}
