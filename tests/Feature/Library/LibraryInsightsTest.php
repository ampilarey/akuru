<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListLibraryInsightsAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\LibraryReadingProgress;
use App\Domains\Library\Models\LibrarySearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B14 (LIBRARY_PLAN §29, STATUS §5it): the Library over a period, for the
 * office — aggregates only, by period, with a CSV; and the shelf's
 * searches recorded so "looked for and not found" can be answered.
 */
function insightsItem(string $title): LibraryItem
{
    $admin = User::factory()->create();
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => $title, 'content_type' => 'book', 'access_type' => 'free_public', 'body' => '<p>'.$title.'</p>', 'created_by' => $admin->id,
    ]);

    return app(PublishLibraryItemAction::class)->execute($item->id, $admin->id)->fresh();
}

function insightsRead(LibraryItem $item, int $readers, int $pagesEach, int $daysAgo): void
{
    for ($r = 0; $r < $readers; $r++) {
        $user = User::factory()->create();
        for ($p = 1; $p <= $pagesEach; $p++) {
            LibraryReadingEvent::query()->create([
                'user_id' => $user->id, 'library_item_id' => $item->id, 'page_number' => $p,
                'session_hash' => str_repeat('s', 16), 'device_hash' => str_repeat('d', 16), 'occurred_at' => now()->subDays($daysAgo),
            ]);
        }
    }
}

it('counts the period: readers, pages, completions, purchases, revenue, and what was most read', function () {
    $sun = insightsItem('Sun letters');
    $moon = insightsItem('Moon letters');
    $old = insightsItem('Read long ago');

    insightsRead($sun, readers: 3, pagesEach: 4, daysAgo: 2);   // 12 pages, 3 readers, this week
    insightsRead($moon, readers: 2, pagesEach: 2, daysAgo: 20); // 4 pages, 2 readers, this month
    insightsRead($old, readers: 5, pagesEach: 5, daysAgo: 200); // 25 pages, only in "all"

    LibraryReadingProgress::query()->create(['user_id' => User::factory()->create()->id, 'library_item_id' => $sun->id, 'current_page' => 4, 'progress_percent' => 100, 'completed_at' => now()->subDay(), 'last_read_at' => now()->subDay()]);
    LibraryPurchase::query()->create(['user_id' => User::factory()->create()->id, 'library_item_id' => $moon->id, 'amount' => 50, 'currency' => 'MVR', 'status' => 'paid', 'purchased_at' => now()->subDays(3)]);
    LibraryPurchase::query()->create(['user_id' => User::factory()->create()->id, 'library_item_id' => $moon->id, 'amount' => 70, 'currency' => 'MVR', 'status' => 'refunded', 'purchased_at' => now()->subDays(3)]);

    $week = app(ListLibraryInsightsAction::class)->execute('week');
    expect($week['headline'])->toMatchArray(['active_readers' => 3, 'pages_opened' => 12, 'completions' => 1, 'purchases' => 1, 'revenue' => '50.00'])
        ->and(array_column($week['most_read'], 'title'))->toBe(['Sun letters'])
        ->and($week['most_read'][0])->toMatchArray(['pages' => 12, 'readers' => 3, 'completions' => 1, 'purchases' => 0]);

    $month = app(ListLibraryInsightsAction::class)->execute('month');
    expect($month['headline']['active_readers'])->toBe(5)
        ->and(array_column($month['most_read'], 'title'))->toBe(['Sun letters', 'Moon letters'])
        ->and($month['most_read'][1]['purchases'])->toBe(1);

    $all = app(ListLibraryInsightsAction::class)->execute('all');
    expect(array_column($all['most_read'], 'title'))->toBe(['Read long ago', 'Sun letters', 'Moon letters'])
        ->and($all['since'])->toBeNull()
        ->and(app(ListLibraryInsightsAction::class)->execute('nonsense')['period'])->toBe('month');

    // The office opens it, and the CSV carries the table for the period.
    $office = actingSystemAdmin(['library.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.insights', ['period' => 'week']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Library/Insights')
            ->where('insights.period', 'week')
            ->where('insights.headline.pages_opened', 12)
            ->where('insights.most_read.0.title', 'Sun letters')
            ->where('t.library_insights_title', 'Digital Library insights'));
    $csv = test()->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.insights.export', ['period' => 'week']))->assertOk()->streamedContent();
    expect($csv)->toContain('period,title,category,writer,pages_opened,readers,completions,purchases')
        ->and($csv)->toContain('week,"Sun letters",')
        ->and($csv)->not->toContain('Read long ago');

    test()->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin(['library.manage']))->get(route('admin.library.insights'))->assertForbidden();
    // The office page reaches it (the link is in the Inertia bundle; `AdminPagesAreReachableTest` lists it as opened from the hub).
    test()->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.library.index'))->assertOk();
});

it('records what the shelf is asked for, and reports what was not found', function () {
    insightsItem('Sun letters');

    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['q' => 'Sun']))->assertOk();
    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['q' => ' sun ']))->assertOk();
    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['q' => 'Tajweed']))->assertOk();
    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['q' => 'tajweed']))->assertOk();
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))->assertOk();

    expect(LibrarySearchLog::query()->count())->toBe(4)
        ->and(LibrarySearchLog::query()->where('term', 'sun')->count())->toBe(2)
        ->and(LibrarySearchLog::query()->where('term', 'sun')->value('hits'))->toBe(1)
        ->and(LibrarySearchLog::query()->where('term', 'tajweed')->value('hits'))->toBe(0);

    $insights = app(ListLibraryInsightsAction::class)->execute('week');
    expect($insights['headline']['searches'])->toBe(4)
        ->and($insights['searches']['top'])->toBe([['term' => 'sun', 'count' => 2, 'misses' => 0], ['term' => 'tajweed', 'count' => 2, 'misses' => 2]])
        ->and($insights['searches']['empty'])->toBe([['term' => 'tajweed', 'count' => 2]]);
});
