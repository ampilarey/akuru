<?php

namespace App\Http\Middleware;

use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Domains\Portal\Actions\ResolveDashboardLandingAction;
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
                // E7: someone with two identities — a teacher who is also a
                // parent — lands on one of them. This is the other one, so the
                // UI can say it exists. Costs no query: Spatie already has the
                // roles in memory, and this runs on every Inertia response.
                'alternate' => $this->alternateIdentity($request),
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
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'gift_card_code' => $request->session()->get('gift_card_code'),
            ],
            'i18n' => [
                'learn' => trans('learn'),
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

    /**
     * The identity `/dashboard` did not land this person on, as a link.
     *
     * Returns null for everyone with a single identity, which is almost
     * everyone — the prop only appears for the teacher-parent case E7 is about.
     *
     * @return ?array{label: string, href: string}
     */
    private function alternateIdentity(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $alternate = app(ResolveDashboardLandingAction::class)
            ->execute($user->getRoleNames()->all())['alternate'];

        if ($alternate === null) {
            return null;
        }

        return [
            'label' => $alternate['label'],
            'href' => route($alternate['route'], [], false),
        ];
    }
}
