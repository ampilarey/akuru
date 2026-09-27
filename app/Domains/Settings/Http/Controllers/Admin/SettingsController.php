<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Settings\Actions\ClearApplicationCachesAction;
use App\Domains\Settings\Actions\ResolveIntegrationStatusAction;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System Settings (docs/ADMIN_PANEL.md). Inertia since C9 slice 1 (STATUS
 * §5jb): the first of the panel's Blade screens ported, with its strings
 * keyed for Dhivehi and Arabic. `role:super_admin` on the route group.
 */
class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', app(ResolveIntegrationStatusAction::class)->execute() + [
            't' => trans('admin'),
        ]);
    }

    public function clearCache(ClearApplicationCachesAction $action)
    {
        $ran = $action->execute(app()->configurationIsCached());

        return back()->with('success', in_array('config:cache', $ran, true)
            ? trans('admin.system_settings_cleared_recached')
            : trans('admin.system_settings_cleared'));
    }
}
