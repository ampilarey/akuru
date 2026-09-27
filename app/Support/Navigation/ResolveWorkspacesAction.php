<?php

namespace App\Support\Navigation;

/**
 * The workspaces one signed-in person holds, and which one is active.
 *
 * Held: every workspace one of their roles opens (`WorkspaceMap`), in the
 * map's order; a person with no role holds their account alone. Active: the
 * one asked for, if held; else the one remembered in the session (the last
 * they switched to, or the last home they visited — `RememberWorkspace`);
 * else the first held. Roles only, no query: Spatie has them in memory and
 * this runs on every response.
 */
class ResolveWorkspacesAction
{
    /**
     * @return array{active: ?string, list: list<array{key: string, label: string, href: string, route: string}>}
     */
    public function execute(?object $user, ?string $requested = null): array
    {
        if ($user === null || ! method_exists($user, 'getRoleNames')) {
            return ['active' => null, 'list' => []];
        }

        $roles = $user->getRoleNames()->all();
        $list = [];
        foreach (WorkspaceMap::all() as $key => $workspace) {
            if (array_intersect($workspace['roles'], $roles) !== []) {
                $list[] = $this->present($key, $roles);
            }
        }
        if ($list === []) {
            $list[] = $this->present(WorkspaceMap::ACCOUNT, $roles);
        }

        $keys = array_column($list, 'key');
        $remembered = app()->bound('session') ? session()->get('workspace') : null;
        $active = null;
        foreach ([$requested, $remembered] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $keys, true)) {
                $active = $candidate;
                break;
            }
        }

        return ['active' => $active ?? $keys[0], 'list' => $list];
    }

    /**
     * @param  list<string>  $roles
     * @return array{key: string, label: string, href: string, route: string}
     */
    private function present(string $key, array $roles): array
    {
        $route = WorkspaceMap::homeFor($key, $roles);
        $label = trans('nav.workspace_'.$key);

        return [
            'key' => $key,
            'label' => $label === 'nav.workspace_'.$key ? $key : $label,
            'href' => route($route, [], false),
            'route' => $route,
        ];
    }
}
