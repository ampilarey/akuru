<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Portal\Actions\ComposeAdminTodayAction;
use App\Domains\Settings\Actions\ListAdminSectionsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/admin`: an administrator's home (docs/ADMIN_PANEL.md §1). Thin: one
 * action decides what this person may open, another what today's numbers
 * are for them; nobody who may open nothing gets in.
 */
class AdminHubController extends Controller
{
    public function index(Request $request, ListAdminSectionsAction $sections, ComposeAdminTodayAction $today): Response
    {
        $parts = $sections->execute($request->user(), app()->getLocale());
        abort_if($parts === [], 403);

        return Inertia::render('Settings/AdminHub', [
            't' => trans('admin'),
            'parts' => $parts,
            'today' => $today->execute($request->user()),
        ]);
    }
}
