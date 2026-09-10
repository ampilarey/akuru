<?php

use Tests\Architecture\Support\ViolationScanner;

/**
 * Every runtime string key the code resolves must actually exist.
 *
 * This generalises the three defects found on 2026-09-10, which were the same
 * bug wearing different clothes — a string looked up at runtime that nothing
 * defined, resolving to null or false instead of throwing:
 *
 *   - `events.manage` / `forms.manage` / `messages.broadcast` (§5bo) — checked
 *     but created by no migration, so three admin screens 403'd for everyone
 *     including super_admin, silently.
 *   - `bml.callback_secret` (§5bp) — a config key that did not exist, sitting
 *     in the fallback of the payment webhook's signature check. Following that
 *     thread found the webhook confirming payments for free.
 *   - `services.bml.api_key` and `services.sms_gateway.url` (§5br) — config
 *     keys that could not answer the question they were asked, so the admin
 *     settings badges were wrong in both directions.
 *
 * None of them threw. That is the whole problem: `config()` returns null,
 * `->can()` returns false, and the feature is simply not there. The permission
 * half of this family already has its own guard in
 * `RoutePermissionsExistTest`; this file covers config keys and route names.
 */
function scannedPhpFiles(string $dir): array
{
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
}

it('resolves every config key the code reads', function () {
    $sources = [
        ...scannedPhpFiles(base_path('app')),
        ...scannedPhpFiles(base_path('routes')),
        ...scannedPhpFiles(base_path('database')),
    ];

    $missing = [];
    foreach ($sources as $file) {
        $source = file_get_contents($file);

        if (! preg_match_all('/\b(?:config|Config::get)\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/', $source, $matches)) {
            continue;
        }

        foreach ($matches[1] as $key) {
            // A trailing dot means the key is built by concatenation
            // (`config('payments.providers.'.$name)`) and cannot be checked
            // statically. `permission.*` is Spatie's own published config.
            if (str_ends_with($key, '.') || str_starts_with($key, 'permission.')) {
                continue;
            }
            if (! config()->has($key)) {
                $missing[$key] = str_replace(base_path().'/', '', $file);
            }
        }
    }

    // If the pattern ever stops matching, this test would pass by finding
    // nothing at all. 80 keys were in use when it was written.
    expect(count($sources))->toBeGreaterThan(100, 'The file scan found almost nothing — check the paths.');

    $report = [];
    foreach ($missing as $key => $file) {
        $report[] = $key.'  (read in '.$file.')';
    }
    sort($report);

    expect($report)->toBe(
        [],
        "config() keys that resolve to null. The caller silently takes the wrong\n"
        ."branch — add the key to its config file, or fix the caller to read the\n"
        ."key that actually holds the value:\n"
        .implode("\n", $report)
    );
});

it('registers every route name the code builds a url for', function () {
    $baseline = require __DIR__.'/Baselines/unregistered_route_names.php';

    $registered = [];
    foreach (app('router')->getRoutes() as $route) {
        if (($name = $route->getName()) !== null) {
            $registered[$name] = true;
        }
    }

    $jsx = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'jsx') {
            $jsx[] = $file->getPathname();
        }
    }

    $sources = [
        ...scannedPhpFiles(base_path('app')),
        ...scannedPhpFiles(base_path('routes')),
        ...scannedPhpFiles(resource_path('views')),
        ...$jsx,
    ];

    $current = [];
    foreach ($sources as $file) {
        $source = file_get_contents($file);

        // Two shapes build a url from a route name: the bare `route('x')`
        // helper, and `redirect()->route('x')`. Two others look identical to a
        // naive pattern and are not urls at all — `Notification::route('mail',
        // …)` sets a notification channel, and `$request->route('id')` reads a
        // route *parameter*. Both of those appeared in the first draft of this
        // scan as false positives, so the distinction is drawn explicitly.
        $names = [];
        foreach ([
            '/(?<![>:\w])route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/',
            '/redirect\(\s*\)\s*->\s*route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/',
        ] as $pattern) {
            if (preg_match_all($pattern, $source, $matches)) {
                $names = array_merge($names, $matches[1]);
            }
        }

        foreach (array_unique($names) as $name) {
            if (! isset($registered[$name])) {
                $current[] = $name.' <- '.str_replace(base_path().'/', '', $file);
            }
        }
    }
    $current = array_values(array_unique($current));
    sort($current);

    expect(count($registered))->toBeGreaterThan(100, 'Almost no named routes — the router did not load.');

    $diff = ViolationScanner::diffBaseline($current, $baseline);

    expect($diff['added'])->toBeEmpty(
        "route() called with a name no route registers — this throws\n"
        ."RouteNotFoundException the moment the call site is reached:\n"
        .implode("\n", $diff['added'])
    );

    expect($diff['removed'])->toBeEmpty(
        "Fixed — remove these from tests/Architecture/Baselines/unregistered_route_names.php\n"
        ."and correct its 'Baseline count' comment:\n"
        .implode("\n", $diff['removed'])
    );
});
