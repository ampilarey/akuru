<?php

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

/**
 * The shell's navigation for one signed-in person: a short primary bar for
 * their roles and the *More* groups, with every link they could only be
 * refused left out.
 *
 * Visibility is read off the **route**, not duplicated here. Each nav href is
 * matched to its GET route and that route's gathered middleware is evaluated
 * for the person — `role:a|b`, `permission:x|y`, `can:ability`,
 * `role_or_permission:` — exactly the checks the request would fail. A link
 * whose route does not exist is hidden too, so the menu cannot carry a dead
 * link. The `roles` hint on `auth`-only pages (portal, learning, teaching)
 * covers what a route cannot say: which kind of person the page is *for*.
 *
 * Labels come back translated (`lang/nav.php`, English fallback), so the
 * shell renders text and nothing else.
 */
class BuildNavigationAction
{
    /**
     * @return array{primary: list<array{key: string, label: string, href: string}>, groups: list<array{key: string, label: string, items: list<array{key: string, label: string, href: string}>}>}
     */
    public function execute(?object $user, string $locale): array
    {
        if ($user === null || ! method_exists($user, 'hasAnyRole')) {
            return ['primary' => [], 'groups' => []];
        }

        $roles = $user->getRoleNames()->all();
        $routes = Route::getRoutes()->getRoutesByMethod()['GET'] ?? [];

        $primary = [];
        $seen = [];
        foreach ($roles as $role) {
            foreach (NavigationMap::barsFor($role) as $bar) {
                foreach (NavigationMap::primary()[$bar] ?? [] as $item) {
                    if (isset($seen[$item['href']]) || ! $this->mayOpen($user, $roles, $item, $routes, $locale)) {
                        continue;
                    }
                    $seen[$item['href']] = true;
                    $primary[] = $this->present($item);
                }
            }
        }

        $groups = [];
        foreach (NavigationMap::groups() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if ($this->mayOpen($user, $roles, $item, $routes, $locale)) {
                    $items[] = $this->present($item);
                }
            }
            if ($items !== []) {
                $groups[] = [
                    'key' => $group['key'],
                    'label' => $this->label($group['key']),
                    'items' => $items,
                ];
            }
        }

        return ['primary' => $primary, 'groups' => $groups];
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
        $path = ltrim($href, '/');

        // Under LaravelLocalization a request's routes carry the locale as a
        // prefix (`en/academics/years`); off the web (tests, the console) they
        // do not. Both are the same route.
        return $routes[$locale.'/'.$path] ?? $routes[$path] ?? null;
    }

    /**
     * @param  array{key: string, href: string}  $item
     * @return array{key: string, label: string, href: string}
     */
    private function present(array $item): array
    {
        return ['key' => $item['key'], 'label' => $this->label($item['key']), 'href' => $item['href']];
    }

    private function label(string $key): string
    {
        $translated = trans('nav.'.$key);

        return $translated === 'nav.'.$key ? $key : $translated;
    }
}
