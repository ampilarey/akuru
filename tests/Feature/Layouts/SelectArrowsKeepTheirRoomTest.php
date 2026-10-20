<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The Library's filters, 2026-10-18 (STATUS §5st): a select carrying the
 * forms plugin's `.form-input` lost the room for its arrow, which sat on
 * the last letters; the header's carets were pulled into their words; and
 * the filters wrapped wherever the widths fell.
 */
uses(RefreshDatabase::class);

it('keeps a select\'s arrow its room, both directions, and the header\'s carets off their words', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toMatch('/select\.form-input:where\([^)]*\)[^{]*\{\s*padding-right:\s*2\.5rem;/')
        ->and($css)->toContain('[dir="rtl"] select.form-input:where(');

    expect(file_get_contents(resource_path('views/components/public/nav.blade.php')))
        ->toContain('.nav-lib-btn {')->not->toContain('margin-inline-start: -.35rem');
});

it('lays the Library\'s filters out in rows: the search, then the sort and the choices, then the ticks', function () {
    $html = $this->withoutLocalizationMiddleware()->get('/library')->assertOk()->getContent();
    $form = substr($html, strpos($html, 'data-testid="shelf-filters"'));
    $form = substr($form, 0, strpos($form, '</form>'));

    // MF2 (STATUS §5sw): the choices sit in the fold under "Filter and sort".
    expect($form)->toContain('class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]"')
        ->and(strpos($form, 'id="library-sort"'))->toBeLessThan(strpos($form, 'id="library-type"'))
        ->and(strpos($form, 'name="peer_reviewed"'))->toBeGreaterThan(strpos($form, 'id="library-reading"'));
});
