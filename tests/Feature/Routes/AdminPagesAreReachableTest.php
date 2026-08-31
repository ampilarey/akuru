<?php

use Illuminate\Support\Facades\Route;

/**
 * A page nobody can navigate to is not shipped.
 *
 * The operator checklist and feature walkthrough were built, permissioned and
 * CI-green, but linked only from the Inertia AppShell — which a Blade landing
 * never renders — so an admin could not find them. Commerce, Library and
 * Pronunciation had no inbound link from any view, and the prayer-times pages
 * only linked to each other. This pins every admin landing page to at least one
 * inbound link so the next one cannot go missing quietly.
 */
function adminLandingRoutes(): array
{
    $skip = '/\.(export|toggle|save|suggest|store|update|destroy|create|edit|show|preview|import)$/';

    return collect(Route::getRoutes())
        ->filter(fn ($r) => $r->getName()
            && str_starts_with($r->getName(), 'admin.')
            && in_array('GET', $r->methods(), true)
            && ! preg_match($skip, $r->getName())
            && ! str_contains($r->uri(), '{'))
        ->mapWithKeys(fn ($r) => [$r->getName() => $r->uri()])
        ->all();
}

it('reaches every admin landing page from the Blade nav', function () {
    // Reachability is measured from the Blade nav, not from "mentioned
    // somewhere". Two real bugs hid behind the weaker rule: pages linked only
    // from the Inertia AppShell (which a Blade landing never renders), and the
    // prayer-times cluster, whose pages linked only to each other.
    //
    // Pages legitimately opened from a parent screen rather than the menu.
    // Add here only with the parent named.
    $allowed = [
        'admin.prayer-times.groups.index' => 'opened from the admin.prayer-times.islands hub',
        'admin.prayer-times.broadcasts.index' => 'opened from the admin.prayer-times.islands hub',
        'admin.daily-content.queue' => 'opened from admin.daily-content.index',
        'admin.daily-content.ayah-preview' => 'opened from admin.daily-content.index',
        'admin.enrollments.payments' => 'opened from admin.enrollments.index',
        'admin.leads.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.funnel.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.research.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.daily-content.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.daily-subscriptions.index' => 'opened from the Website CMS hub (admin.pages.index)',
    ];

    $nav = (string) file_get_contents(resource_path('views/layouts/navigation.blade.php'));
    $orphans = [];

    foreach (adminLandingRoutes() as $name => $uri) {
        if (array_key_exists($name, $allowed)) {
            continue;
        }
        $linked = str_contains($nav, "route('{$name}')")
            || str_contains($nav, "route(\"{$name}\")");

        if (! $linked) {
            $orphans[] = "{$name}  (/{$uri})";
        }
    }

    expect($orphans)->toBeEmpty(
        "Admin pages not reachable from the Blade nav — an admin on a Blade\n"
        ."dashboard can only get to these by typing the URL:\n  "
        .implode("\n  ", $orphans)
        ."\nAdd a nav entry gated by the same permission the route checks, or list\n"
        ."it in \$allowed naming the parent screen it opens from.\n"
        .'A link in AppShell.jsx does NOT count: Blade landings never render it.'
    );
});

it('gates the operations nav entries on the permission the routes require', function () {
    $nav = (string) file_get_contents(resource_path('views/layouts/navigation.blade.php'));

    // The routes require can:operations.manage / can:translations.manage; the
    // nav must gate on the same thing, so what is shown matches what is allowed.
    expect($nav)->toContain("@can('operations.manage')")
        ->and($nav)->toContain("route('admin.operations.index')")
        ->and($nav)->toContain("route('admin.operations.features')")
        ->and($nav)->toContain("@can('translations.manage')");
});
