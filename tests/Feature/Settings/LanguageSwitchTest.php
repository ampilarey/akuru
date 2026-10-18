<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Actions\ListEnabledLocalesAction;
use App\Http\Middleware\RedirectDisabledLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * STATUS §5su: Dhivehi and Arabic each switch off on System Settings. English
 * is always on. A language that is off leaves the language menus, the
 * sitemap and the hreflang links, and its addresses open the page in English.
 */
uses(RefreshDatabase::class);

it('has every language on until the office switches one off', function () {
    expect(app(ListEnabledLocalesAction::class)->execute())->toBe(['en', 'dv', 'ar']);

    test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin())->get('/admin/settings')
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Index')
            ->where('languages', ['dv' => true, 'ar' => true])
            ->where('locales', ['en', 'dv', 'ar']));
});

it('switches Arabic off: the menus, the sitemap, the hreflang links and the locale address leave it out', function () {
    $office = actingSystemAdmin();
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($office)->put('/admin/settings/languages', ['dv' => true, 'ar' => false])
        ->assertSessionHas('success', __('admin.system_settings_languages_saved'));
    expect(app(ListEnabledLocalesAction::class)->execute())->toBe(['en', 'dv']);

    $web->actingAs($office)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
        ->where('languages', ['dv' => true, 'ar' => false])
        ->where('locales', ['en', 'dv'])
        ->where('locale_urls', fn ($urls) => array_keys(collect($urls)->all()) === ['en', 'dv']));

    auth()->logout();
    $sitemap = $web->get('/sitemap.xml')->assertOk()->getContent();
    expect($sitemap)->toContain('/dv')->not->toContain('/ar/')->not->toContain('hreflang="ar"');

    $home = $web->get('/library')->assertOk()->getContent();
    expect($home)->toContain('hreflang="dv"')->not->toContain('hreflang="ar"')
        ->toContain('data-code="dv"')->not->toContain('data-code="ar"');

    $web->get('/locale/ar');
    expect(session('locale'))->not->toBe('ar');
    $web->get('/locale/dv');
    expect(session('locale'))->toBe('dv');

    // Switched back on, it is as it was.
    $web->actingAs($office)->put('/admin/settings/languages', ['dv' => true, 'ar' => true]);
    expect(app(ListEnabledLocalesAction::class)->execute())->toBe(['en', 'dv', 'ar']);
});

it('sends an address in a language that is off to the same page in English, query kept', function () {
    app(\App\Domains\Settings\Actions\SetEnabledLocalesAction::class)->execute(['dv' => false, 'ar' => true]);
    $middleware = new RedirectDisabledLocale;
    $through = fn () => response('through');

    $request = Request::create('/dv/library/blogs?language=dv&page=2');
    $request->setLaravelSession(app('session.store'));
    $response = $middleware->handle($request, $through);
    expect($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toEndWith('/en/library/blogs?language=dv&page=2')
        ->and(session('locale'))->toBe('en');

    expect($middleware->handle(Request::create('/dv'), $through)->headers->get('Location'))->toEndWith('/en');
    expect($middleware->handle(Request::create('/ar/library'), $through)->getContent())->toBe('through');
    expect($middleware->handle(Request::create('/dv/library/x', 'POST'), $through)->getContent())->toBe('through');
    expect($middleware->handle(Request::create('/dvd-catalogue'), $through)->getContent())->toBe('through');
});

it('lets only the super admin switch a language, and never English', function () {
    $user = User::factory()->create();
    test()->withoutLocalizationMiddleware()->actingAs($user)->put('/admin/settings/languages', ['dv' => false, 'ar' => false])->assertForbidden();
    expect(app(ListEnabledLocalesAction::class)->execute())->toBe(['en', 'dv', 'ar']);

    test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin())->put('/admin/settings/languages', ['en' => false, 'dv' => false, 'ar' => false])->assertSessionHasNoErrors();
    expect(app(ListEnabledLocalesAction::class)->execute())->toBe(['en']);
    test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin())->put('/admin/settings/languages', ['dv' => 'maybe'])->assertSessionHasErrors(['dv', 'ar']);
});

it('names the switch in three languages', function () {
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['system_settings_languages', 'system_settings_languages_help', 'system_settings_language_en_always', 'system_settings_languages_save', 'system_settings_languages_saved'] as $key) {
            expect(__('admin.'.$key, [], $locale))->not->toBe('admin.'.$key);
        }
    }
});
