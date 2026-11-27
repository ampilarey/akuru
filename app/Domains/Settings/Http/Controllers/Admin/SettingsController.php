<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Settings\Actions\ClearApplicationCachesAction;
use App\Domains\Settings\Actions\ListEnabledLocalesAction;
use App\Domains\Settings\Actions\ResolveIntegrationStatusAction;
use App\Domains\Settings\Actions\SetEnabledLocalesAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System Settings (docs/ADMIN_PANEL.md). Inertia since C9 slice 1 (STATUS
 * §5jb): the first of the panel's Blade screens ported, with its strings
 * keyed for Dhivehi and Arabic. `role:super_admin` on the route group.
 */
class SettingsController extends Controller
{
    /**
     * ST1 (STATUS §5ut): the Settings index — one card per product's own
     * settings page, for whoever may open at least one of the Institute's.
     */
    public function home(Request $request): Response
    {
        $cards = app(\App\Domains\Settings\Actions\ListSettingsIndexAction::class)->execute($request->user(), app()->getLocale());
        // The index is the Institute's: the School's cards are links for whoever also holds one of its own (ADR-040).
        abort_unless(collect($cards)->contains('group', 'institute'), 403);

        return Inertia::render('Settings/Home', ['cards' => $cards, 't' => Phrases::once('admin')]);
    }

    /** The system's own page: health, languages, caches, backups. */
    public function index(): Response
    {
        return Inertia::render('Settings/Index', app(ResolveIntegrationStatusAction::class)->execute() + [
            // STATUS §5su: Dhivehi and Arabic each switch on or off.
            'languages' => app(ListEnabledLocalesAction::class)->states(),
            // BK2: the nightly backups.
            'backup' => app(\App\Domains\Settings\Actions\ResolveBackupStatusAction::class)->execute(),
            't' => Phrases::once('admin'),
        ]);
    }

    public function languages(Request $request): RedirectResponse
    {
        $validated = $request->validate(collect(ListEnabledLocalesAction::SWITCHABLE)->mapWithKeys(fn (string $locale) => [$locale => 'required|boolean'])->all());
        app(SetEnabledLocalesAction::class)->execute(array_map(fn ($on) => filter_var($on, FILTER_VALIDATE_BOOLEAN), $validated));

        return back()->with('success', trans('admin.system_settings_languages_saved'));
    }

    public function clearCache(ClearApplicationCachesAction $action)
    {
        $ran = $action->execute(app()->configurationIsCached());

        return back()->with('success', in_array('config:cache', $ran, true)
            ? trans('admin.system_settings_cleared_recached')
            : trans('admin.system_settings_cleared'));
    }
}
