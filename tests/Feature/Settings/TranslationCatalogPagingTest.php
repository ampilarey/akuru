<?php

use App\Domains\Settings\Actions\ListTranslationCatalogAction;
use App\Domains\Settings\Actions\SaveTranslationOverrideAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The translation editor is paged (ADMIN_PANEL.md §7 P5, STATUS §5no): the
 * group chips, the search and the suspect filter are the URL, and the
 * server answers with 25 rows of the active group. The whole catalog used
 * to travel on every visit (743 rows, 132 KB) and the active group's 185
 * rows drew at once, each a textarea. The CSV still carries everything.
 */
uses(RefreshDatabase::class);

it('sends one page of the active group with every group summarised', function () {
    $admin = actingSystemAdmin(['translations.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Translations')
            ->where('locale', 'dv')
            ->where('active_group', 'common')
            ->has('items', 25)
            ->has('groups', count(ListTranslationCatalogAction::groups()))
            ->where('groups.0.group', 'common')
            ->where('groups.0.count', fn ($count) => $count > 25)
            ->where('pagination.current_page', 1)
            ->where('pagination.next', fn ($url) => str_contains((string) $url, 'page=2') && str_contains((string) $url, 'group=common'))
            ->where('filters', ['q' => '', 'suspect' => false])
            ->where('total', fn ($total) => $total > 500));
});

it('turns the page, switches group, searches and keeps only the suspect rows', function () {
    $admin = actingSystemAdmin(['translations.manage']);

    $second = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pagination.current_page', 2)->where('pagination.prev', fn ($url) => is_string($url) && ! str_contains($url, 'page=')));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['group' => 'learn']))->assertOk()
        // The group's own rows, not the catalog's: its count in the group
        // summary (a fixed bound broke the day the book grew past it).
        ->assertInertia(fn (Assert $page) => $page->where('active_group', 'learn')
            ->where('pagination.total', fn ($total) => $total > 0
                && $total === collect($page->toArray()['props']['groups'])->firstWhere('group', 'learn')['count']
                && $total < $page->toArray()['props']['total']));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['q' => 'dashboard']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', 'dashboard')
            ->where('items', fn ($items) => count($items) > 0 && collect($items)->every(fn ($item) => str_contains($item['key'], 'dashboard') || str_contains(strtolower($item['en']), 'dashboard') || str_contains((string) $item['file_value'], 'dashboard'))));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['suspect' => 1]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.suspect', true)
            ->where('items', fn ($items) => collect($items)->every(fn ($item) => $item['suspect'] === true)));

    // An unknown group and an absurd page fall back rather than 500.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['group' => 'nope', 'page' => 999]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('active_group', 'common')->where('pagination.current_page', fn ($p) => $p >= 1));
});

it('counts a saved correction in the summary and still exports the whole catalog', function () {
    $admin = actingSystemAdmin(['translations.manage']);
    app(SaveTranslationOverrideAction::class)->execute('common', 'dashboard', 'ޑޭޝްބޯޑު — ރަނގަޅު', (int) $admin->id, 'dv');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['q' => 'dashboard']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('override_count', 1)
            ->where('items', fn ($items) => collect($items)->contains(fn ($item) => $item['key'] === 'dashboard' && $item['override'] === 'ޑޭޝްބޯޑު — ރަނގަޅު')));

    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.export', ['locale' => 'dv']))->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBeGreaterThan(500)
        ->and($csv)->toContain('ޑޭޝްބޯޑު — ރަނގަޅު');
});
