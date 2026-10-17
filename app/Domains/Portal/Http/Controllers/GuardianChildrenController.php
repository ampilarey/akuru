<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Library\Actions\Blog\ListBlogRequestsAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianChildrenController extends Controller
{
    public function index(Request $request): Response
    {
        $list = app(ListGuardianChildrenAction::class);
        $userId = (int) $request->user()->id;

        $children = $list->executeForGuardianUserId($userId);

        return Inertia::render('Portal/Children', [
            'children' => $children,
            // BLOG_PLAN BL1: a child's request to start a blog, by student id.
            'blogs' => app(ListBlogRequestsAction::class)->latestForStudents($children->pluck('id')->map(fn ($id) => (int) $id)->all()),
            // Item 13: links the office has not verified yet, by name only.
            'pending' => $list->executePendingForGuardianUserId($userId),
            't' => Phrases::once('portal'),
        ]);
    }
}
