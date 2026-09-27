<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STATUS §5ja. The guest layout (login, register, reset) and the public home
 * page draw a faint SVG pattern through an inline `style` attribute. The
 * pattern was written as `url(\"data:…\")` — a backslash is not an escape
 * inside an HTML attribute, so the attribute ended at the first quote, the
 * pattern never drew, and every guest page fetched an image at
 * `/en/%EF%BF%BD` (the CSS parser's replacement character) and got a 404.
 * Seen in the B3 walk's console, never by a person: it is a background.
 *
 * The quotes are entities now. This pins the rendered HTML, not the source,
 * so a copy of the pattern into a third template is caught the same way.
 */
it('renders the decorative SVG pattern as one well-formed style attribute on the guest and home pages', function () {
    foreach ([route('login'), url('/')] as $url) {
        $html = $this->withoutLocalizationMiddleware()->get($url)->assertOk()->getContent();

        expect($html)->not->toContain('url(\\"')
            ->and($html)->not->toContain("\u{FFFD}")
            ->and(preg_match('~style="[^"]*background-image:url\(&quot;data:image/svg\+xml,[^"]*&quot;\)[^"]*"~', $html))->toBe(1, 'the pattern should sit inside one style attribute on '.$url);
    }
});
