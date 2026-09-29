<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STATUS §5lp: the public site reads right to left in Dhivehi and Arabic, as
 * the app shell and the reader already did; English stays left to right.
 */
it('sets the page direction from the language', function (string $locale, string $dir) {
    app()->setLocale($locale);

    $html = view('public.layouts.public')->render();

    expect($html)->toContain('<html lang="'.$locale.'" dir="'.$dir.'">')
        ->and($html)->toContain('[dir="rtl"] .rtl-flip');
})->with([['en', 'ltr'], ['dv', 'rtl'], ['ar', 'rtl']]);

it('hides the translation holder without pushing it off to one side', function () {
    $nav = file_get_contents(resource_path('views/components/public/nav.blade.php'));

    // -9999px to the left is real sideways scroll in a right-to-left page.
    expect($nav)->not->toContain('left:-9999px')->toContain('id="google_translate_element"');
});
