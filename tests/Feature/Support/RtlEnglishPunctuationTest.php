<?php

/**
 * English sentences on Dhivehi and Arabic pages (owner decision 9, STATUS
 * §5gd). The rendered behaviour is walked by `scripts/smoke/rtl.mjs`, which
 * measures glyph positions in Chromium; this pins the stylesheet so the rule
 * cannot quietly lose one of its halves.
 */
function appCss(): string
{
    return (string) file_get_contents(resource_path('css/app.css'));
}

it('gives each text element on an RTL page the direction of its own first letter', function () {
    expect(appCss())->toMatch('/:where\(\[dir="rtl"\]\) :where\(p, li, [^)]*td[^)]*\) \{\s*unicode-bidi: plaintext;/');
});

it('keeps the page alignment, physically, where nothing else sets it', function () {
    $css = appCss();

    // start → right, end → left, on RTL pages only.
    expect($css)->toMatch('/:where\(:not\([^{]*text-center[^{]*\)\) \{\s*text-align: right;/')
        ->and($css)->toMatch('/\[dir="rtl"\] :is\([^)]*\)\.text-start \{\s*text-align: right;/')
        ->and($css)->toMatch('/\[dir="rtl"\] :is\([^)]*\)\.text-end \{\s*text-align: left;/');
});

it('does not lean on text-align: match-parent, which Chrome drops', function () {
    // The first version did, and only the bidi half applied: every English
    // line on a Dhivehi page went left. The RTL walk caught it.
    $rules = preg_replace('#/\*.*?\*/#s', '', appCss());

    expect($rules)->not->toContain('match-parent');
});

it('leaves table headers and captions to their own centring', function () {
    $selector = [];
    preg_match('/:where\(\[dir="rtl"\]\) :where\(([^)]*)\) \{\s*unicode-bidi/', appCss(), $selector);

    $elements = array_map('trim', explode(',', $selector[1] ?? ''));

    expect($elements)->toContain('td', 'p')
        ->and($elements)->not->toContain('th')
        ->and($elements)->not->toContain('caption');
});

it('marks the shell flash messages so the rule reaches them', function () {
    $shell = file_get_contents(resource_path('js/Layouts/AppShell.jsx'));

    expect($shell)->toContain('role="status"')
        ->and($shell)->toContain('role="alert"');
});
