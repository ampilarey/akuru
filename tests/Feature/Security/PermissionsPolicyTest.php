<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The header that closed Arabic B's student surface.
 *
 * `Permissions-Policy: microphone=()` is an **empty allowlist** — it denies
 * every origin, this one included. `/learn/pronounce` calls
 * `getUserMedia({audio: true})`, so the Record button threw `NotAllowedError`
 * on every browser for every student, and the error handling told them to allow
 * microphone access for the site, which no browser setting can do against a
 * response header.
 *
 * It arrived with the E8 pick-up slice — a slice with nothing to do with
 * recording — and **no test named this header**, so nothing noticed for as long
 * as nobody walked §1d. These are that test.
 */
it('lets this origin ask for the microphone', function () {
    $policy = $this->withoutLocalizationMiddleware()->get(route('login'))->headers->get('Permissions-Policy');

    expect($policy)->toContain('microphone=(self)')
        // The bug, stated as the thing that must never come back: an empty
        // allowlist reads as `microphone=()` and denies everyone.
        ->and($policy)->not->toMatch('/microphone=\(\s*\)/');
});

it('keeps the doors nothing asks for shut', function () {
    $policy = $this->withoutLocalizationMiddleware()->get(route('login'))->headers->get('Permissions-Policy');

    // Nothing in the app calls either. A feature that needs one opens its own
    // door in its own slice, rather than inheriting it from here.
    expect($policy)->toContain('geolocation=()')
        ->and($policy)->toContain('camera=()');
});

it('still sends the rest of the security headers', function () {
    $response = $this->withoutLocalizationMiddleware()->get(route('login'));

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Strict-Transport-Security'))->toContain('max-age=');
});
