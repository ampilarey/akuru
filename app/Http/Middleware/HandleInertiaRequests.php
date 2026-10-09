<?php

namespace App\Http\Middleware;

use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Support\Inertia\Phrases;
use App\Support\Navigation\ResolveWorkspacesAction;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

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
     * A redirect to another site, answering an Inertia visit, becomes a full
     * browser visit (STATUS §5px).
     *
     * Inertia sends a visit as an XHR, and an XHR follows a 302 by itself.
     * When the 302 points at the bank's payment page, that is a cross-origin
     * request the bank does not answer for, so the browser refuses it: the
     * Pay button on a family's fees page and Enroll on a paid course did
     * nothing at all. `Inertia::location()` is the protocol's answer — a 409
     * that tells the page to go there itself, the way logging out already
     * leaves the shell (STATUS §5mw).
     *
     * Here rather than in each controller, so a new payment door cannot forget
     * it: every `redirect()->away()` an Inertia page reaches is covered, and a
     * redirect within this site is left as it was.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->header('X-Inertia') && $response->isRedirect() && $this->leavesThisSite($request, (string) $response->headers->get('Location'))) {
            return Inertia::location((string) $response->headers->get('Location'));
        }

        return $response;
    }

    /** A relative address, or one on this host, stays on the site. */
    private function leavesThisSite(Request $request, string $location): bool
    {
        $host = parse_url($location, PHP_URL_HOST);

        return is_string($host) && $host !== '' && strcasecmp($host, $request->getHost()) !== 0;
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
                // Only ever signed in with a one-time code: the shell asks them
                // to choose a password on their workspace home, whichever it is
                // (docs/SIGN_IN_PLAN.md ID3).
                'must_set_password' => (bool) $request->user()?->force_password_change,
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
                // ID5: the Messages tile on a workspace home carries its count.
                'unread_messages' => $request->user() === null ? 0 : app(\App\Domains\Notifications\Actions\ListMessageInboxAction::class)->unreadCount((int) $request->user()->id),
            ],
            // The shell's navigation, built for this person: a short primary
            // bar for their roles and the *More* groups, with every link they
            // could only be refused left out (docs/APPSHELL_NAV_IA.md). Read
            // off each route's own guard, so it cannot drift from the gates.
            'nav' => app(\App\Support\Navigation\BuildNavigationAction::class)->execute($request->user(), $locale, $workspaces['active']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                // A word that is neither: the registration flow's "you are
                // already enrolled in …" when it sends a person to My
                // enrolments (docs/SIGN_IN_PLAN.md ID2b).
                'info' => $request->session()->get('info'),
                'gift_card_code' => $request->session()->get('gift_card_code'),
                // B1a: a new vendor member's one-time password, shown once to
                // whoever added them (the office or the shop's owner).
                'vendor_invite' => $request->session()->get('vendor_invite'),
                'temporary_password' => $request->session()->get('temporary_password'),
            ],
        ];
    }

    /**
     * The shell's strings, sent once per locale and remembered by the client
     * (docs/ADMIN_PANEL.md §7 P2, STATUS §5nm): 6 KB that every page used to
     * carry on every visit. Keyed by locale, so a language switch fetches the
     * other book; bounded by the same TTL as a page's phrase book.
     *
     * @return array<string, OnceProp>
     */
    public function shareOnce(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'i18n' => Inertia::once(fn () => [
                'learn' => trans('learn'),
                // The shell's own words — the *More* button and the menu's
                // labels; item labels arrive already translated in `nav`.
                'nav' => array_intersect_key((array) trans('nav'), array_flip(['more', 'close', 'primary_nav', 'all_screens', 'alerts', 'skip_to_content', 'dashboard_hint', 'workspaces', 'switch_workspace', 'workspace_home', 'set_password_title', 'set_password_body', 'set_password_link', 'language', 'your_accounts', 'tab_bar', 'tab_sheet_hint'])),
                // Only the page-facing subset: sharing the whole group would
                // serialize every common string into every page's payload
                // (and unrelated strings then leak into page assertions).
                'common' => array_filter(
                    (array) trans('common'),
                    fn ($value, $key) => is_string($value)
                        && (str_starts_with($key, 'library_') || str_starts_with($key, 'review_')),
                    ARRAY_FILTER_USE_BOTH,
                ),
            ])->as('i18n:'.$locale)->until(now()->addMinutes(Phrases::TTL_MINUTES)),
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
