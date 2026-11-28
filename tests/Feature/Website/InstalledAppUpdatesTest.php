<?php

/*
 * PW1 (STATUS §5uz): an installed app is told when a new version is out,
 * clears the files a deploy retired, and refreshes its images and fonts.
 */

it('serves every page with the build it was made from, and the words for the update bar', function () {
    $build = hash_file('sha256', public_path('build/manifest.json'));

    foreach (['en' => 'A new version of Akuru is ready.', 'dv' => 'އަކުރުގެ އައު ވާޝަނެއް ތައްޔާރުވެއްޖެ.', 'ar' => 'نسخة جديدة من أكورو جاهزة.'] as $locale => $ready) {
        app()->setLocale($locale);
        $html = view('partials.pwa')->render();
        expect($html)->toContain('<meta name="app-build" content="'.$build.'">')
            ->toContain(json_encode($ready))
            ->toContain('data-testid\', \'update-bar\'')
            ->toContain("fetch('/build/manifest.json', { cache: 'no-store'")
            ->toContain("postMessage({ type: 'prune' })");
        foreach (['update_ready', 'update_refresh', 'update_later'] as $key) {
            expect(trans("common.{$key}"))->not->toBe("common.{$key}");
        }
    }
    // All three shells carry it.
    foreach (['app.blade.php', 'layouts/app.blade.php', 'public/layouts/public.blade.php'] as $shell) {
        expect((string) file_get_contents(resource_path("views/{$shell}")))->toContain("@include('partials.pwa')");
    }
});

it('never caches a page or the build manifest, prunes retired build files and refreshes images and fonts', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("const CACHE = 'akuru-shell-v2'")
        ->toContain('url.pathname === BUILD_MANIFEST')
        ->toContain("event.data.type === 'prune'")
        ->toContain("path.startsWith('/build/') && !keep.has(path)")
        ->toContain('if (refreshed) {')
        ->toContain("fetch(event.request).catch(() => caches.match('/offline.html'))");
    $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/');
    expect(is_file(public_path('build/manifest.json')))->toBeTrue();
});
