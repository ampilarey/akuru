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

it('colours the logo with the brand palette and nothing from the old one', function () {
    $logo = file_get_contents(public_path('images/logos/akuru-logo.svg'));
    $onDark = file_get_contents(public_path('images/logos/akuru-logo-on-dark.svg'));

    expect($logo)->toContain('#7C2D37')->toContain('#A8861F')
        ->and($onDark)->toContain('#FFFFFF')->toContain('#C9A227')
        ->and($logo.$onDark)->not->toContain('#0878D8')->not->toContain('#585858');
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
