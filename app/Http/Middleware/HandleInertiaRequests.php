<?php

namespace App\Http\Middleware;

use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * E7: accounts the signed-in person may switch to.
     *
     * Shared rather than fetched per page because the plan's acceptance is
     * *"switches in two taps"*, and a switcher that lives only on a settings
     * screen is four.
     *
     * @return list<array{id: int, name: string, roles: string}>
     */
    private function linkedAccounts(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return app(\App\Domains\Identity\Actions\ListLinkedAccountsAction::class)
            ->execute((int) $user->id)
            ->all();
    }

    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        // The workspaces this person holds and the active one (STATUS §5id):
        // the shell shows one at a time and a switcher for the others.
        $workspaces = app(ResolveWorkspacesAction::class)->execute($request->user());

        return [
            ...parent::share($request),
            'locale' => $locale,
            'locales' => ['en', 'dv', 'ar'],
            'locale_urls' => collect(['en', 'dv', 'ar'])->mapWithKeys(
                fn (string $code) => [$code => LaravelLocalization::getLocalizedURL($code) ?: '/'.$code],
            )->all(),
            'rtl' => in_array($locale, ['ar', 'dv'], true),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
                // Nav-only hints; every route still enforces its own gate.
                'can' => [
                    'operations_manage' => (bool) $request->user()?->can('operations.manage'),
                    'translations_manage' => (bool) $request->user()?->can('translations.manage'),
                ],
                // The active workspace and every one this person holds, so
                // the shell can offer the switch from any page (STATUS §5id).
                'workspace' => $workspaces['active'],
                'workspaces' => $workspaces['list'],
                // E7: the accounts this person has proved they also own, so
                // the switch is two taps from any screen rather than a trip to
                // a settings page. One indexed lookup on a tiny table, and it
                // short-circuits to nothing for everybody who has no links —
                // which is almost everybody.
                'linked_accounts' => $this->linkedAccounts($request),
                // E22a: five features have been writing notifications nobody
                // could see. One indexed COUNT per Inertia response is the
                // price of them being discoverable from any page.
                'unread_notifications' => $this->unreadNotifications($request),
            ],
            // The shell's navigation, built for this person: a short primary
            // bar for their roles and the *More* groups, with every link they
            // could only be refused left out (docs/APPSHELL_NAV_IA.md). Read
            // off each route's own guard, so it cannot drift from the gates.
            'nav' => app(\App\Support\Navigation\BuildNavigationAction::class)->execute($request->user(), $locale, $workspaces['active']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'gift_card_code' => $request->session()->get('gift_card_code'),
                // B1a: a new vendor member's one-time password, shown once to
                // whoever added them (the office or the shop's owner).
                'vendor_invite' => $request->session()->get('vendor_invite'),
                'temporary_password' => $request->session()->get('temporary_password'),
            ],
            'i18n' => [
                'learn' => trans('learn'),
                // The shell's own words — the *More* button and the menu's
                // labels; item labels arrive already translated in `nav`.
                'nav' => array_intersect_key((array) trans('nav'), array_flip(['more', 'close', 'primary_nav', 'all_screens', 'alerts', 'skip_to_content', 'dashboard_hint', 'workspaces', 'switch_workspace', 'workspace_home'])),
                // Only the page-facing subset: sharing the whole group would
                // serialize every common string into every page's payload
                // (and unrelated strings then leak into page assertions).
                'common' => array_filter(
                    (array) trans('common'),
                    fn ($value, $key) => is_string($value)
                        && (str_starts_with($key, 'library_') || str_starts_with($key, 'review_')),
                    ARRAY_FILTER_USE_BOTH,
                ),
            ],
        ];
    }

    private function unreadNotifications(Request $request): int
    {
        $user = $request->user();

        return $user === null
            ? 0
            : app(ListUserNotificationsAction::class)->unreadCount((int) $user->id);
    }
}
