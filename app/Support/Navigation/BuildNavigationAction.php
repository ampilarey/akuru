<?php

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

/**
 * The shell's navigation for one signed-in person in one workspace: the
 * workspace's primary bar for their roles and its *More* groups, with every
 * link they could only be refused left out.
 *
 * Visibility is read off the **route**, not duplicated here. Each nav href is
 * matched to its GET route and that route's gathered middleware is evaluated
 * for the person — `role:a|b`, `permission:x|y`, `can:ability`,
 * `role_or_permission:` — exactly the checks the request would fail. A link
 * whose route does not exist is hidden too, so the menu cannot carry a dead
 * link. The `roles` hint on `auth`-only pages (portal, learning, teaching)
 * covers what a route cannot say: which kind of person the page is *for*.
 *
 * The workspace (`WorkspaceMap`, STATUS §5id) decides which bar and which
 * groups are offered — the Institute's, the School's, a family's — never
 * whether a link may be opened. Given none, the person's active workspace
 * is resolved: the one they last switched to, else the first they hold.
 *
 * Labels come back translated (`lang/nav.php`, English fallback), so the
 * shell renders text and nothing else.
 */
class BuildNavigationAction
{
    /**
     * @return array{primary: list<array{key: string, label: string, href: string}>, groups: list<array{key: string, label: string, items: list<array{key: string, label: string, href: string}>}>, tabs?: list<array{key: string, label: string}>, workspace?: string}
     */
    public function execute(?object $user, string $locale, ?string $workspace = null): array
    {
        if ($user === null || ! method_exists($user, 'hasAnyRole')) {
            return ['primary' => [], 'groups' => []];
        }

        $roles = $user->getRoleNames()->all();
        $routes = Route::getRoutes()->getRoutesByMethod()['GET'] ?? [];
        $workspace ??= app(ResolveWorkspacesAction::class)->execute($user)['active'] ?? WorkspaceMap::ACCOUNT;

        $primary = [];
        $seen = [];
        foreach (WorkspaceMap::barsFor($workspace, $roles) as $bar) {
            foreach (NavigationMap::primary()[$bar] ?? [] as $item) {
                if (isset($seen[$item['href']]) || ! $this->mayOpen($user, $roles, $item, $routes, $locale)) {
                    continue;
                }
                $seen[$item['href']] = true;
                $primary[] = $this->present($item);
            }
        }

        $all = [];
        foreach (NavigationMap::groups() as $group) {
            $all[$group['key']] = $group;
        }
        $groups = [];
        foreach (WorkspaceMap::definition($workspace)['groups'] as $key) {
            $group = $all[$key] ?? null;
            if ($group === null) {
                continue;
            }
            $items = [];
            foreach ($group['items'] as $item) {
                if (! $this->belongsIn($item, $workspace) || ! $this->mayOpen($user, $roles, $item, $routes, $locale)) {
                    continue;
                }
                // A section's inner screens (the admin panel), each behind
                // its own route gate too.
                $children = [];
                foreach ($item['children'] ?? [] as $child) {
                    if ($this->mayOpen($user, $roles, $child, $routes, $locale)) {
                        $children[] = $this->present($child);
                    }
                }
                $items[] = $this->present($item, $children);
            }
            if ($items !== []) {
                $groups[] = [
                    'key' => $key,
                    'label' => $this->label($key),
                    'items' => $items,
                ];
            }
        }

        // The phone's tab bar (ADMIN_PANEL.md §7 M7): one tab per group the
        // workspace names, only where that group survived the gates above —
        // a tab can never open onto an empty sheet. Labels are the short
        // `tab_*` phrase where there is one, the group's own where not.
        $present = array_column($groups, 'key');
        $tabs = [];
        foreach (WorkspaceMap::tabsFor($workspace) as $key) {
            if (in_array($key, $present, true)) {
                $tabs[] = ['key' => $key, 'label' => $this->tabLabel($key)];
            }
        }

        return ['primary' => $primary, 'groups' => $groups, 'tabs' => $tabs, 'workspace' => $workspace];
    }

    /**
     * A group several workspaces share (Communication) may hold items that
     * belong to some of them only: a family's fees are the household's, not
     * the School's a teacher-parent is also in. The person's roles cannot say
     * that — a teacher-parent holds both — so the item names its workspaces.
     *
     * @param  array{workspaces?: list<string>}  $item
     */
    private function belongsIn(array $item, string $workspace): bool
    {
        return ! isset($item['workspaces']) || in_array($workspace, $item['workspaces'], true);
    }

    /**
     * @param  array{key: string, href: string, roles?: ?list<string>, can?: list<string>}  $item
     * @param  list<string>  $roles
     * @param  array<string, RouteInstance>  $routes
     */
    private function mayOpen(object $user, array $roles, array $item, array $routes, string $locale): bool
    {
        if (array_key_exists('roles', $item) && $item['roles'] !== null && array_intersect($roles, $item['roles']) === []) {
            return false;
        }

        // A gate the controller applies rather than the route (`abort_unless
        // can(...)`): the map mirrors it, and any one of the abilities admits.
        if (isset($item['can']) && ! ($user instanceof Authorizable && collect($item['can'])->contains(fn (string $ability) => $user->can($ability)))) {
            return false;
        }

        $route = $this->routeFor($item['href'], $routes, $locale);
        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $layer) {
            if (! is_string($layer)) {
                continue;
            }
            [$name, $argument] = array_pad(explode(':', $layer, 2), 2, '');
            $options = $argument === '' ? [] : explode('|', explode(',', $argument)[0]);

            $allowed = match ($name) {
                'role' => $user->hasAnyRole($options),
                'permission' => $user->hasAnyPermission($options),
                'role_or_permission' => $user->hasAnyRole($options) || $user->hasAnyPermission($options),
                'can' => $user instanceof Authorizable && $options !== [] && $user->can($options[0]),
                default => true,
            };

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, RouteInstance>  $routes
     */
    private function routeFor(string $href, array $routes, string $locale): ?RouteInstance
    {
        // An anchor (`/admin/bookshop#settings`) names a section of a page;
        // the route, and its gate, are the page's.
        $path = ltrim(NavigationMap::path($href), '/');

        // Under LaravelLocalization a request's routes carry the locale as a
        // prefix (`en/academics/years`); off the web (tests, the console) they
        // do not. Both are the same route.
        return $routes[$locale.'/'.$path] ?? $routes[$path] ?? null;
    }

    /**
     * @param  array{key: string, href: string, hard?: bool}  $item
     * @param  list<array{key: string, label: string, href: string, hard?: bool}>  $children
     * @return array{key: string, label: string, href: string, hard?: bool, children?: list<array{key: string, label: string, href: string, hard?: bool}>}
     */
    private function present(array $item, array $children = []): array
    {
        $presented = ['key' => $item['key'], 'label' => $this->label($item['key']), 'href' => $item['href']];
        // A Blade screen: the shell must load it whole, not as an Inertia visit.
        if (! empty($item['hard'])) {
            $presented['hard'] = true;
        }
        if ($children !== []) {
            $presented['children'] = $children;
        }

        return $presented;
    }

    private function label(string $key): string
    {
        $translated = trans('nav.'.$key);

        return $translated === 'nav.'.$key ? $key : $translated;
    }

    /** A tab is a word wide: `tab_panel_money` is "Shops" where the group is "Shops & money". */
    private function tabLabel(string $key): string
    {
        $translated = trans('nav.tab_'.$key);

        return $translated === 'nav.tab_'.$key ? $this->label($key) : $translated;
    }
}
