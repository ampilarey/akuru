<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Library\Actions\Blog\ConsentToBlogRequestAction;
use App\Domains\Library\Actions\Blog\ListBlogRequestsAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use App\Support\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BLOG_PLAN BL1 (§4.1, D7): a parent sees their child's blog request and
 * agrees or says no. Verified children only (item 13), as every page under
 * a child is.
 */
class GuardianChildBlogController extends Controller
{
    public function show(Request $request, int $student): Response
    {
        $child = $this->child($request, $student);
        abort_if($child === null, 403);
        $latest = app(ListBlogRequestsAction::class)->latestForStudents([$student])[$student] ?? null;

        return Inertia::render('Portal/ChildBlog', [
            'child' => ['id' => (int) $child->id, 'name' => PersonName::ofStudent($child)],
            'request' => $latest,
            't' => Phrases::once('blog'),
        ]);
    }

    public function consent(Request $request, int $student): RedirectResponse
    {
        abort_if($this->child($request, $student) === null, 403);
        $data = $request->validate([
            'request_id' => 'required|integer',
            'agree' => 'required|boolean',
            'show_photo' => 'boolean',
            'show_school' => 'boolean',
        ]);
        $latest = app(ListBlogRequestsAction::class)->latestForStudents([$student])[$student] ?? null;
        abort_unless($latest !== null && $latest['id'] === (int) $data['request_id'], 404);

        app(ConsentToBlogRequestAction::class)->execute((int) $data['request_id'], (int) $request->user()->id, (bool) $data['agree'], (bool) ($data['show_photo'] ?? false), (bool) ($data['show_school'] ?? false));

        return back()->with('success', __($data['agree'] ? 'blog.flash_parent_agreed' : 'blog.flash_parent_declined'));
    }

    /** The named child, if they are one of this parent's verified children. */
    private function child(Request $request, int $student): ?object
    {
        return app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->first(fn (object $row) => (int) $row->id === $student);
    }
}
