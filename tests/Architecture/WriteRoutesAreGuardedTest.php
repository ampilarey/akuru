<?php

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

/**
 * SPEC §45 "Policies and Permissions":
 *
 *   > Backend must enforce permissions.
 *
 * and SPEC §44 "API Requirements":
 *
 *   > All actions must use backend policies and permissions.
 *   > **Do not rely only on frontend button hiding.**
 *
 * Both are claims about *every* write route, and nothing checked them. The
 * defect that prompted this test: `POST /announcements` was `auth`-only with no
 * check in the controller body, so any signed-in account — a pupil, a parent —
 * could post a school-wide announcement, `type: emergency` and `priority:
 * urgent` included, at any audience or class. The screen was admin-only; the
 * route was not. That is precisely "relying on frontend button hiding".
 *
 * It is the same defect, in the same file, as the students/teachers block
 * twenty lines above it, which an earlier slice had already fixed with this
 * comment: *"Any signed-in account — a parent, a pupil — could therefore list,
 * create, edit and delete students and teachers through these."* That slice
 * fixed three route groups and walked past the fourth. A person reading route
 * definitions will do that again; a test will not.
 *
 * **What this test does and does not claim.** The detector below is
 * deliberately narrow — it recognises route middleware, `abort_*`,
 * `$this->authorize()`, a `FormRequest::authorize()` that is not `return true`,
 * and a few named helpers. It cannot see a guard that lives inside a called
 * Action or behind a custom helper, and this codebase has plenty of both
 * (rule 5 puts rules in Actions). So a route in the baseline is **not**
 * asserted to be unsafe — many were read and found correct, and each carries
 * its reason.
 *
 * The claim is narrower and still worth making: **the set cannot grow
 * silently.** A new write route with no visible guard has to be looked at and
 * written down before it can merge.
 *
 * Filesystem and router only: no database, no HTTP, no fixture.
 */
it('guards every write route, or names the exception in the baseline', function () {
    $baseline = require __DIR__.'/Baselines/unguarded_write_routes.php';
    $unguarded = [];

    foreach (Route::getRoutes() as $route) {
        if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            continue;
        }
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            continue;
        }
        [$class, $method] = explode('@', $action);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            continue;
        }

        // A route guarded by middleware needs nothing in the controller.
        $middleware = implode(' ', $route->gatherMiddleware());
        if (preg_match('/\b(role|permission|can|role_or_permission|signed):/', $middleware)) {
            continue;
        }

        if (routeMethodIsGuarded($class, $method)) {
            continue;
        }

        $unguarded[] = $route->uri();
    }

    $unguarded = array_values(array_unique($unguarded));
    sort($unguarded);

    $new = array_values(array_diff($unguarded, array_keys($baseline)));

    expect($new)->toBeEmpty(
        "These write routes have no guard this test can see:\n  "
        .implode("\n  ", $new)
        ."\n\nSPEC §45: \"Backend must enforce permissions\". §44: \"Do not rely only on "
        ."frontend button hiding.\"\n\nEither add one — route middleware (`role:`, `permission:`, "
        .'`can:`), `abort_unless($request->user()?->can(…), 403)`, `$this->authorize(…)`, or a '
        .'FormRequest whose `authorize()` actually checks something — or, if the route is '
        ."genuinely public or scoped to the caller's own data, add it to "
        .'tests/Architecture/Baselines/unguarded_write_routes.php with the reason.'
    );

    // The baseline may only shrink. A stale entry is a guard someone added
    // without deleting the note that says it is missing, and the next reader
    // believes the note.
    $stale = array_values(array_diff(array_keys($baseline), $unguarded));

    expect($stale)->toBeEmpty(
        "These baseline entries are now guarded and should be deleted:\n  "
        .implode("\n  ", $stale)
        ."\n\nThe baseline may only shrink."
    );
});

function routeMethodIsGuarded(string $class, string $method): bool
{
    $reflection = new ReflectionMethod($class, $method);
    $file = $reflection->getFileName();
    if ($file === false) {
        return false;
    }

    $source = implode('', array_slice(
        file($file),
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1,
    ));

    // `authorizeClassSubject()` is a real example: a named helper doing the
    // check, which no generic pattern would catch.
    if (preg_match('/abort_unless|abort_if|->authorize[A-Za-z]*\(|Gate::|->can\(|policy\(|validateApiKey|hasRole/', $source)) {
        return true;
    }

    foreach ($reflection->getParameters() as $parameter) {
        $type = $parameter->getType();
        if (! $type instanceof ReflectionNamedType || ! class_exists($type->getName())) {
            continue;
        }
        if (! is_subclass_of($type->getName(), FormRequest::class)) {
            continue;
        }

        // `authorize()` returning a bare `true` is the absence of a check
        // wearing the shape of one.
        $requestSource = (string) file_get_contents((new ReflectionClass($type->getName()))->getFileName());
        if (preg_match('/function authorize\(\)[^{]*\{\s*return\s+(?!true\s*;)/', $requestSource)) {
            return true;
        }
    }

    return false;
}
