<?php

namespace App\Support\Navigation;

use App\Domains\Courses\Actions\HoldsOwnLearningAction;

/**
 * The workspaces one signed-in person holds, and which one is active.
 *
 * Held: every workspace one of their roles opens (`WorkspaceMap`), in the
 * map's order, and *My learning* when they have learning of their own
 * (docs/SIGN_IN_PLAN.md ID2a); a person with neither holds their account
 * alone. Active: the one asked for, if held; else the one remembered in the
 * session (the last they switched to, or the last home they visited —
 * `RememberWorkspace`); else the first held. The roles are in memory; the
 * one query, whether they learn, is asked once per request per person,
 * since this runs several times on every response.
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
            $holds = $key === WorkspaceMap::LEARNER
                ? $this->learns($user, $roles)
                : array_intersect($workspace['roles'], $roles) !== [];
            if ($holds) {
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
     * *My learning* is derived, not granted: a login that owns a student
     * record with a live course enrolment. A school pupil (`student`) already
     * has their courses in *Learn*, so they do not hold it twice.
     *
     * @param  list<string>  $roles
     */
    private function learns(object $user, array $roles): bool
    {
        if (in_array('student', $roles, true) || ! isset($user->id)) {
            return false;
        }

        $key = 'workspaces.learns.'.$user->id;
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, app(HoldsOwnLearningAction::class)->execute((int) $user->id));
        }

        return (bool) $attributes->get($key);
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
