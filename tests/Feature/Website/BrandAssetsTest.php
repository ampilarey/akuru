<?php

/**
 * The logo and the app icons are in the brand palette, and every file the
 * site asks for exists under the exact name it asks for (docs/BRAND.md).
 *
 * Until 2026-09-24 the logo was blue and grey against a wine-and-gold site,
 * and it was committed as `akuru-logo.PNG` while the component asked for
 * `akuru-logo.png` — a Linux server does not find one when asked for the
 * other, so a fresh install showed a fallback in a colour that does not
 * exist. Both servers worked only because someone had copied a lowercase
 * file onto each by hand.
 */
it('serves every logo the component can ask for, under its exact name', function () {
    foreach (['akuru-logo.svg', 'akuru-logo-on-dark.svg', 'akuru-logo-white.svg', 'akuru-mark.svg', 'akuru-logo-800.png'] as $file) {
        $path = public_path('images/logos/'.$file);
        expect(file_exists($path))->toBeTrue("missing {$file}")
            // Exact case, even on a case-insensitive filesystem.
            ->and(in_array($file, scandir(dirname($path)), true))->toBeTrue("{$file} is not spelled that way on disk");
    }

    expect(file_exists(public_path('images/logos/akuru-logo.PNG')))->toBeFalse();
});

it('colours the logo with the owner\'s artwork palette and nothing from the old one', function () {
    // Since 2026-09-29 the files come from the owner's own vector artwork
    // (docs/BRAND.md): wine #6E1E25 and gold #C9A227.
    $logo = strtolower(file_get_contents(public_path('images/logos/akuru-logo.svg')));
    $onDark = strtolower(file_get_contents(public_path('images/logos/akuru-logo-on-dark.svg')));
    $white = strtolower(file_get_contents(public_path('images/logos/akuru-logo-white.svg')));

    expect($logo)->toContain('#6e1e25')->toContain('#c9a227')
        ->and($onDark)->toContain('#ffffff')->toContain('#c9a227')->not->toContain('#6e1e25')
        // One colour: white, with INSTITUTE knocked out of its bar.
        ->and($white)->not->toContain('#c9a227')->not->toContain('#6e1e25')->toContain('mask="url(#akuru-knockout)"')
        ->and($logo.$onDark)->not->toContain('#0878d8')->not->toContain('#585858');
});

it('has no background of its own, so it sits on any colour', function () {
    foreach (['akuru-logo.svg', 'akuru-logo-on-dark.svg', 'akuru-logo-white.svg', 'akuru-mark.svg'] as $file) {
        $svg = file_get_contents(public_path('images/logos/'.$file));
        // The design tool's export carried a white 360 x 180 rectangle behind everything.
        expect($svg)->not->toContain('<rect x="-30"', $file)
            ->and($svg)->toMatch('#<svg [^>]*viewBox="[\\d. ]+"#', $file);
    }
    // The owner's uploads are built into these files and removed (names with spaces, and duplicates).
    expect(glob(public_path('images/logos/Copy of*')))->toBe([]);
});

it('renders the right file for each background', function (string $variant, string $file) {
    $html = (string) $this->blade('<x-akuru-logo variant="'.$variant.'" />');

    expect($html)->toContain('images/logos/'.$file)->not->toContain('brightness-0');
})->with([
    ['default', 'akuru-logo.svg'],
    ['on-dark', 'akuru-logo-on-dark.svg'],
    ['white', 'akuru-logo-white.svg'],
]);

it('uses the on-dark logo on every wine background, rather than inverting it to white', function () {
    foreach (['components/public/footer.blade.php', 'layouts/navigation.blade.php', 'layouts/guest.blade.php'] as $view) {
        $source = file_get_contents(resource_path('views/'.$view));

        expect($source)->toContain('variant="on-dark"')
            ->and($source)->not->toContain('class="brightness-0 invert"');
    }
});

it('ships every icon the pages and the manifest link, at the size they claim', function () {
    $icons = [
        'images/favicon-16x16.png' => 16,
        'images/favicon-32x32.png' => 32,
        'images/apple-touch-icon.png' => 180,
        'images/pwa-192.png' => 192,
        'images/pwa-512.png' => 512,
    ];
    foreach ($icons as $path => $size) {
        [$width, $height] = getimagesize(public_path($path));
        expect([$width, $height])->toBe([$size, $size], $path);
    }

    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim(strtok($icon['src'], '?'), '/'))))->toBeTrue($icon['src']);
    }

    expect(file_get_contents(public_path('favicon.ico'), false, null, 0, 4))->toBe("\x00\x00\x01\x00");
});

it('gives search engines the logo, not an app icon', function () {
    $jsonLd = app(\App\Domains\Website\Actions\ComposeOrganizationJsonLdAction::class)->execute();

    expect(json_encode($jsonLd))->toContain('images\/logos\/akuru-logo-800.png');
});

it('carries the revised wordmark of 2026-09-30, and the site asks for it anew', function () {
    // The revised artwork's AKURU "A" (STATUS §5lv); the old one began "M 38.640625".
    foreach (['akuru-logo.svg', 'akuru-logo-on-dark.svg', 'akuru-logo-white.svg'] as $file) {
        expect(file_get_contents(public_path('images/logos/'.$file)))->toContain('M 38.96875 -0.65625')->not->toContain('M 38.640625 -0.65625');
    }
    // The emblem did not change, so neither did the mark or the icons.
    expect(file_get_contents(public_path('images/logos/akuru-mark.svg')))->not->toContain('#6e1e25')->toContain('viewBox="4.5 1.625 93 145.875"');

    expect((string) $this->blade('<x-akuru-logo />'))->toContain('akuru-logo.svg?v=5')
        ->and(file_get_contents(resource_path('js/Layouts/AppShell.jsx')))->toContain('akuru-logo-on-dark.svg?v=5');
    [$width] = getimagesize(public_path('images/logos/akuru-logo-800.png'));
    expect($width)->toBe(800);
});
