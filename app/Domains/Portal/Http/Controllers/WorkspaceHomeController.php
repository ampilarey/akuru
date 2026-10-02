<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Portal\Actions\ComposeAdminTodayAction;
use App\Domains\Portal\Actions\ComposeWorkspaceHomeAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A workspace's home: `/admin` for the Institute, `/school` for the School
 * (docs/ADMIN_PANEL.md §1, STATUS §5id). Thin: one action says whether this
 * is the person's workspace, one composes the page, one the day's numbers.
 * A person who does not hold the workspace is sent to their own home, not
 * refused.
 */
class WorkspaceHomeController extends Controller
{
    public function institute(Request $request): Response|RedirectResponse
    {
        return $this->home($request, 'institute');
    }

    public function school(Request $request): Response|RedirectResponse
    {
        return $this->home($request, 'school');
    }

    private function home(Request $request, string $workspace): Response|RedirectResponse
    {
        $user = $request->user();
        $held = collect(app(ResolveWorkspacesAction::class)->execute($user)['list'])->contains('key', $workspace);
        if (! $held) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Portal/WorkspaceHome', [
            't' => Phrases::once('admin'),
            'workspace' => $workspace,
            'parts' => app(ComposeWorkspaceHomeAction::class)->execute($user, $workspace, app()->getLocale()),
            'today' => app(ComposeAdminTodayAction::class)->execute($user, $workspace),
        ]);
    }
}
