<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * docs/MOBILE_FIRST.md rule 3 (MF2, STATUS §5sw): on a phone a listing's
 * filters fold under "Filter and sort" so its results start on the first
 * screen. Each page serves the fold open — it works without script — with
 * the search outside it, a count of the filters in use on its button, and
 * the results marked `data-phone-main` for `phone-first.mjs`. The public
 * layout folds it on a phone.
 */
uses(RefreshDatabase::class);

dataset('listings', [
    'library' => ['/library', 'shelf-filters', 'name="q"'],
    'courses' => ['/courses', 'courses-filters', 'name="search"'],
    'lending' => ['/lending', 'lending-filters', 'name="q"'],
]);

it('folds a listing\'s filters under Filter and sort, the search outside, the results marked', function (string $path, string $form, string $search) {
    $html = test()->withoutLocalizationMiddleware()->get($path)->assertOk()->getContent();
    $start = strpos($html, 'data-testid="'.$form.'"');
    $fold = strpos($html, 'data-filter-fold', $start);

    expect($start)->not->toBeFalse()
        ->and($fold)->not->toBeFalse()
        ->and(strpos($html, $search, $start))->toBeLessThan($fold)
        ->and($html)->toMatch('/<details class="filter-fold[^"]*" open data-filter-fold/')
        ->and($html)->toContain(e(__('shop.filter_and_sort')))
        ->and($html)->toContain('data-phone-main')
        ->and($html)->toContain("document.querySelectorAll('details[data-filter-fold]')");
})->with('listings');

it('counts the filters in use on the button, and keeps the search when filtering courses', function () {
    $web = test()->withoutLocalizationMiddleware();
    expect($web->get('/library?access=free&language=dv')->getContent())->toContain('<span class="filter-fold-count">2</span>');
    expect($web->get('/library')->getContent())->not->toContain('filter-fold-count');

    // One form now: the search travels with the filters.
    $courses = $web->get('/courses?search=arabic&level=kids')->getContent();
    $form = substr($courses, strpos($courses, 'data-testid="courses-filters"'));
    expect(substr($form, 0, strpos($form, '</form>')))->toContain('name="search"')->toContain('name="level"')
        ->and($courses)->toContain('<span class="filter-fold-count">1</span>');
});
