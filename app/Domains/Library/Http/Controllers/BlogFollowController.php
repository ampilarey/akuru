<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\BuildBlogFeedAction;
use App\Domains\Library\Actions\Blog\FollowBlogAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * BLOG_PLAN BL4: following a blog, from its page or *My Library*, and the
 * blogs' RSS feeds. Following needs a signed-in reader; a guest is sent to
 * sign in and comes back.
 */
class BlogFollowController extends Controller
{
    public function follow(Request $request, string $slug): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->guest(route('login'));
        }
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['follow' => 'required|boolean', 'email' => 'nullable|boolean']);
        app(FollowBlogAction::class)->execute((int) $request->user()->id, $slug, (bool) $data['follow'], array_key_exists('email', $data) ? (bool) $data['email'] : null);

        return back()->with('success', __($data['follow'] ? 'public.follow_flash_following' : 'public.follow_flash_stopped'));
    }

    public function authorFeed(string $slug): Response
    {
        $xml = app(BuildBlogFeedAction::class)->forWriter($slug);
        abort_if($xml === null, 404);

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    public function blogsFeed(): Response
    {
        return response(app(BuildBlogFeedAction::class)->forAll(), 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
