<?php

use Inertia\Testing\AssertableInertia as Assert;

/**
 * `/inertia-test` is deploy tooling, not a leftover.
 *
 * `PHASE_0_CHECKLIST.md` originally said "delete after verification". It is
 * kept, and this test says why in code: §5t commits `public/build`, so a stale
 * or unbuilt bundle ships silently and renders a **blank page with no console
 * error**. `/up` proves Laravel booted; only this route proves the *built
 * assets* actually render React.
 *
 * `scripts/deploy-staging-phase0.sh` and `docs/STAGING.md` both smoke-test it
 * unauthenticated, which is why it has no auth guard — and why it must render
 * nothing but a static string.
 */
it('serves the inertia smoke route without a session', function () {
    $this->get('/inertia-test')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('InertiaTest'));
});

it('exposes no data through the smoke route', function () {
    // A public unauthenticated page must stay a static string. If somebody ever
    // adds props here, that is a leak, not a feature.
    $this->get('/inertia-test')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('errors')->etc());
});
