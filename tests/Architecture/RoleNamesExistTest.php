<?php

use App\Support\Authorization\RoleLabels;

/**
 * Every role the code checks is a role somebody can hold (STATUS §5ps).
 *
 * The pronunciation review queue admitted `['super_admin', 'admin', 'teacher',
 * 'supervisor', 'dean']`. No role is called `dean`: the dean is `headmaster`,
 * which the screens label *Dean* (ADR-040 slice 3). So the dean, whom the list
 * was written to let in, was refused, and the navigation, written to match,
 * never offered the door. A misspelt role fails closed and says nothing, so
 * nothing noticed. This reads every role name the app checks — a role gate on
 * a user or a route, and the navigation's and workspaces' role lists — and
 * holds each to the roles the users screen can grant.
 */
function checkedRoleNames(): array
{
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));
    $sources = [];
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $sources[$file->getPathname()] = file_get_contents($file->getPathname());
        }
    }
    foreach (glob(base_path('routes/*.php')) as $path) {
        $sources[$path] = file_get_contents($path);
    }

    foreach ($sources as $path => $source) {
        $where = str_replace(base_path().'/', '', $path);
        // hasRole('x'), hasAnyRole(['x', 'y']), assignRole('x'), …
        preg_match_all("/(?:hasRole|hasAnyRole|hasAllRoles|assignRole|removeRole|syncRoles)\\(\\s*(\\[[^\\]]*\\]|'[^']+')/", $source, $calls);
        // The navigation's and the workspaces' `'roles' => [...]` — only there:
        // elsewhere `'roles' => [...]` is a validation rule.
        if (str_starts_with($where, 'app/Support/Navigation/')) {
            preg_match_all("/'roles'\\s*=>\\s*(\\[[^\\]\$]*\\])/", $source, $lists);
            $calls[1] = [...$calls[1], ...$lists[1]];
        }
        foreach ($calls[1] as $call) {
            preg_match_all("/'([a-z_]+)'/", $call, $names);
            foreach ($names[1] as $name) {
                $found[] = [$where, $name];
            }
        }
        // Route middleware: 'role:super_admin|admin'.
        preg_match_all("/'role:([a-z_|,]+)'/", $source, $middleware);
        foreach ($middleware[1] as $list) {
            foreach (preg_split('/[|,]/', $list) as $name) {
                $found[] = [$where, $name];
            }
        }
    }

    return $found;
}

it('checks no role that nobody can hold', function () {
    $unknown = [];
    foreach (checkedRoleNames() as [$where, $name]) {
        if (! in_array($name, RoleLabels::KNOWN, true)) {
            $unknown[] = "{$where}: {$name}";
        }
    }

    expect(array_values(array_unique($unknown)))->toBe([], 'A role gate names a role no user can be given — it fails closed and says nothing');
});

it('finds the role checks it is meant to read', function () {
    // A pattern that stopped matching would pass the test above with nothing
    // read; these are known to be there.
    $names = array_column(checkedRoleNames(), 1);

    expect($names)->toContain('super_admin', 'headmaster', 'teacher', 'parent', 'lender')
        ->and(count($names))->toBeGreaterThan(100);
});
