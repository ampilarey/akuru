<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The workspace switcher's target: make one of this person's workspaces the
 * active one and go to its home (STATUS §5id). A workspace they do not hold
 * is not there to switch to.
 */
class WorkspaceController extends Controller
{
    public function switch(Request $request, string $workspace, ResolveWorkspacesAction $workspaces): RedirectResponse
    {
        $target = collect($workspaces->execute($request->user())['list'])->firstWhere('key', $workspace);
        abort_if($target === null, 404);

        $request->session()->put('workspace', $workspace);

        return redirect($target['href']);
    }
}
