<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The website's frame after the 2026-09-28 redesign (STATUS §5ki): the header
 * is built around the four products — Courses, Digital Library, Bookstore,
 * School — with everything about the institute under About; the phone's
 * bottom bar is Home · Courses · Library · Shop · Account; prayer times stay
 * exactly where they were.
 */
function siteFramePage(string $route = 'public.library.index')
{
    return test()->withoutLocalizationMiddleware()->get(route($route));
}

it('leads with the four products and keeps the institute under About', function () {
    $html = siteFramePage()->assertOk()->getContent();

    foreach ([
        'courses' => route('public.courses.index'),
        'library' => route('public.library.index'),
        'bookstore' => route('public.shop.index'),
        'school' => route('public.admissions.create'),
    ] as $key => $href) {
        expect($html)->toMatch('#href="'.preg_quote($href, '#').'"\s+data-testid="nav-'.$key.'"#');
    }

    preg_match('#data-testid="nav-about-menu">(.*?)</div>#s', $html, $about);
    expect($about)->not->toBeEmpty();
    foreach (['public.about', 'public.news.index', 'public.articles.index', 'public.research.index', 'public.events.index',
        'public.gallery.index', 'public.achievements', 'public.careers', 'public.contact.create'] as $name) {
        expect($about[1])->toContain('href="'.route($name).'"');
    }

    // The old flat row is gone: news, gallery and the rest are not top-level links any more.
    preg_match('#data-testid="site-nav">(.*?)data-testid="nav-about"#s', $html, $row);
    expect($row[1])->not->toContain(route('public.news.index'))
        ->and($row[1])->not->toContain(route('public.gallery.index'));
});

it('marks the product you are in', function () {
    $html = siteFramePage('public.library.index')->getContent();

    expect($html)->toMatch('#data-testid="nav-library"\s+class="nav-link is-active"\s+aria-current="page"#')
        ->and($html)->toMatch('#data-testid="bottom-library"\s+aria-current="page"#')
        ->and($html)->not->toMatch('#data-testid="nav-courses"\s+class="nav-link is-active"#');
});

it('keeps the prayer times unchanged, in the header on every width', function () {
    $html = siteFramePage()->getContent();

    // Once in the desktop row and once in the phone/tablet header, as before.
    expect(substr_count($html, 'data-block="prayer_bar"'))->toBe(2)
        ->and($html)->toContain('header-prayer header-prayer--mobile nav-mobile-only');
});

it('gives the phone a bottom bar of Home, Courses, Library, Shop and Account', function () {
    $html = siteFramePage()->getContent();

    preg_match('#data-testid="bottom-bar"(.*?)</nav>#s', $html, $bar);
    expect($bar)->not->toBeEmpty();
    preg_match_all('#data-testid="bottom-([a-z-]+)"#', $bar[1], $tabs);
    expect($tabs[1])->toBe(['home', 'courses', 'library', 'shop', 'login'])
        ->and($bar[1])->toContain('href="'.route('login').'"')
        // Viber moved to the floating chat button, which a phone now shows too.
        ->and($bar[1])->not->toContain('viber://')
        ->and($html)->toContain('data-testid="viber-float"');
});

it('opens a phone menu with search, the products, About and Call us', function () {
    $html = siteFramePage()->getContent();

    preg_match('#data-testid="mobile-menu"(.*?)data-testid="mobile-menu-about"(.*?)</div>#s', $html, $menu);
    expect($menu)->not->toBeEmpty()
        ->and($menu[1])->toContain('action="'.route('public.search').'"')
        ->and($menu[1])->toContain('name="q"')
        ->and(substr_count($menu[1], 'class="nav-m-icon"'))->toBe(4)
        ->and($menu[2])->toContain('href="'.route('public.research.index').'"')
        ->and($menu[2])->toContain('href="tel:');
});

it('sends a signed-in person from the bottom bar into their own home', function () {
    $html = $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.index'))->assertOk()->getContent();

    expect($html)->toMatch('#href="'.preg_quote(route('dashboard'), '#').'" data-testid="bottom-my-portal"#')
        ->and($html)->not->toContain('data-testid="bottom-login"')
        ->and($html)->toContain('data-testid="nav-my-portal-mobile"');
});

it('speaks Dhivehi and Arabic in the frame', function () {
    foreach (['dv' => 'ފޮތްފިހާރަ', 'ar' => 'متجر الكتب'] as $locale => $bookstore) {
        app()->setLocale($locale);
        expect(__('site.bookstore'))->toBe($bookstore)
            ->and(__('site.about_akuru'))->not->toBe('site.about_akuru');
    }
});
