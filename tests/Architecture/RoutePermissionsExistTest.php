<?php

/**
 * Every permission the code checks must be created by a **migration**.
 *
 * A permission that is checked but never created fails closed and silently.
 * `->can('forms.manage')` on a permission with no row returns false for
 * everyone — Spatie's gate check swallows `PermissionDoesNotExist` and falls
 * through to a Gate with no matching ability, and this app defines no
 * `Gate::before` super-admin bypass. The screen 403s for every account
 * including super_admin, with nothing in the log to say why. There is no
 * error to notice; the feature simply is not there.
 *
 * The migration part matters as much as the existence part.
 * `scripts/pull-deploy-test.sh` runs `php artisan migrate --force` and never
 * `db:seed`, so a permission that lives only in `RoleSeeder` never reaches a
 * deployment that was set up before it was added. That is not hypothetical:
 * `events.manage`, `forms.manage` and `messages.broadcast` were all seeder-only
 * and all added after the repo started — `forms.manage` on the same day this
 * test was written. `2026_09_10_000010_seeder_only_route_permissions` moved
 * them; this test is what stops the next one drifting back.
 *
 * A hard assertion with no baseline: all 34 pass today, so there is nothing to
 * grandfather.
 */
it('creates every checked permission in a migration', function () {
    $phpFiles = function (string $dir): array {
        if (! is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    };

    // Dotted names only. The legacy snake_case permissions (`manage_users`,
    // `view_hifz_reports`) are matched by the same patterns but are not
    // distinguishable from ordinary method or variable names, so this guard
    // covers the convention in current use rather than guessing at those.
    $checked = [];
    foreach ([...$phpFiles(base_path('app')), ...$phpFiles(base_path('routes'))] as $file) {
        $source = file_get_contents($file);

        // Route middleware: can:a.b, can:a.b|c.d, permission:a.b
        if (preg_match_all('/(?:can|permission):([a-z0-9_.|\-]+)/i', $source, $matches)) {
            foreach ($matches[1] as $group) {
                foreach (explode('|', $group) as $name) {
                    if (str_contains($name, '.')) {
                        $checked[$name][] = $file;
                    }
                }
            }
        }

        // Direct calls: ->can('a.b'), hasPermissionTo('a.b'), …
        if (preg_match_all('/(?:->can|hasPermissionTo|checkPermissionTo|hasAnyPermission)\(\s*[\'"]([a-z0-9_.\-]+)[\'"]/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                if (str_contains($name, '.')) {
                    $checked[$name][] = $file;
                }
            }
        }
    }

    // Sanity: if the patterns ever stop matching, this test would pass by
    // finding nothing at all. 34 permissions were in use when it was written.
    expect(count($checked))->toBeGreaterThanOrEqual(30, 'The scan found almost no permissions — the patterns have drifted.');

    $migrations = array_map('file_get_contents', $phpFiles(base_path('database/migrations')));

    $offenders = [];
    foreach ($checked as $name => $files) {
        foreach ($migrations as $migration) {
            if (str_contains($migration, "'".$name."'") || str_contains($migration, '"'.$name.'"')) {
                continue 2;
            }
        }
        $offenders[] = $name.'  (checked in '.str_replace(base_path().'/', '', $files[0]).')';
    }
    sort($offenders);

    expect($offenders)->toBe(
        [],
        "These permissions are checked in code but created by no migration, so a deployment\n"
        ."that runs `migrate` without `db:seed` refuses them to everyone — super_admin included,\n"
        ."with no error logged. Create them in a migration (see\n"
        ."database/migrations/2026_09_10_000010_seeder_only_route_permissions.php):\n"
        .implode("\n", $offenders)
    );
});
