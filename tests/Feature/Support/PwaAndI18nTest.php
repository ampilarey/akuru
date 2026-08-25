<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('serves the pwa manifest service worker and offline page', function () {
    $manifest = $this->get(route('pwa.manifest'))
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json');
    $payload = json_decode($manifest->streamedContent() ?: $manifest->getContent(), true);
    expect($payload['name'])->toBe('Akuru Institute')
        ->and($payload['start_url'])->toBe('/learn')
        ->and($payload['display'])->toBe('standalone');

    $sw = $this->get(route('pwa.service-worker'))->assertOk();
    $swBody = $sw->streamedContent() ?: $sw->getContent();
    expect($swBody)->toContain('akuru-shell-v1')->and($swBody)->toContain('/offline.html');

    $offline = $this->get(route('pwa.offline'))->assertOk();
    $offlineBody = $offline->streamedContent() ?: $offline->getContent();
    expect($offlineBody)->toContain('You are offline');
});

it('keeps learn strings aligned across en dv and ar', function () {
    $en = trans('learn', [], 'en');
    $dv = trans('learn', [], 'dv');
    $ar = trans('learn', [], 'ar');

    expect(array_keys($dv))->toEqual(array_keys($en))
        ->and(array_keys($ar))->toEqual(array_keys($en))
        ->and($en['upcoming_sessions'])->not->toBeEmpty()
        ->and($dv['locale_dv'])->not->toBeEmpty()
        ->and($ar['locale_ar'])->toBe('العربية');
});

it('shows thaana and arabic font classes on the i18n preview', function () {
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.i18n.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/I18nPreview')
            ->where('samples.1.font', 'thaana')
            ->where('samples.1.dir', 'rtl')
            ->where('samples.2.font', 'arabic')
            ->has('locale_urls.en')
            ->has('locale_urls.dv')
            ->has('locale_urls.ar'));
});
