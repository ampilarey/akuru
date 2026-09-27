<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B5 — the remaining shelf filters (LIBRARY_PLAN §8.2–§8.4, STATUS §5in):
 * difficulty, the reading-time band, popular this week or month, and for
 * research whether a peer has reviewed it and whether it is open to all.
 */
function filterShelfItem(string $title, array $extra = []): LibraryItem
{
    $admin = User::factory()->create();
    $item = app(SaveLibraryItemAction::class)->execute(array_merge([
        'title' => $title,
        'content_type' => 'article',
        'access_type' => 'free_public',
        'body' => '<p>'.$title.'</p>',
        'created_by' => $admin->id,
    ], $extra));

    return app(PublishLibraryItemAction::class)->execute($item->id, $admin->id)->fresh();
}

function filterShelfTitles(array $filters): array
{
    return array_column(app(ListLibraryItemsAction::class)->execute($filters), 'title');
}

it('filters by difficulty and by the reading-time band', function () {
    filterShelfItem('Sun letters', ['difficulty' => 'beginner', 'reading_time' => 5]);
    filterShelfItem('Moon letters', ['difficulty' => 'advanced', 'reading_time' => 20]);
    filterShelfItem('Shadda rules', ['reading_time' => 45]);
    filterShelfItem('No time given');

    expect(filterShelfTitles(['difficulty' => 'beginner']))->toBe(['Sun letters'])
        ->and(filterShelfTitles(['difficulty' => 'advanced']))->toBe(['Moon letters'])
        ->and(filterShelfTitles(['difficulty' => 'nonsense']))->toHaveCount(4)
        ->and(filterShelfTitles(['reading' => 'short']))->toBe(['Sun letters'])
        ->and(filterShelfTitles(['reading' => 'medium']))->toBe(['Moon letters'])
        ->and(filterShelfTitles(['reading' => 'long']))->toBe(['Shadda rules'])
        ->and(filterShelfTitles(['reading' => 'short', 'difficulty' => 'advanced']))->toBe([]);

    // The shelf offers both, and the CSV carries both.
    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['difficulty' => 'beginner', 'reading' => 'short']))
        ->assertOk()
        ->assertSee('Sun letters')
        ->assertDontSee('Moon letters')
        ->assertSee('name="difficulty"', false)
        ->assertSee('name="reading"', false)
        ->assertSee('name="peer_reviewed"', false);

    $csv = $this->withoutLocalizationMiddleware()->get(route('public.library.export', ['difficulty' => 'beginner']))->assertOk()->streamedContent();
    expect($csv)->toContain('difficulty,reading_time')
        ->and($csv)->toContain('Sun letters')
        ->and($csv)->toContain(',beginner,5,')
        ->and($csv)->not->toContain('Moon letters');

    // And the item page says how hard it is.
    $item = LibraryItem::query()->where('title', 'Sun letters')->firstOrFail();
    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->assertOk()->assertSee('Beginner');
});

it('ranks by what was read this week or this month', function () {
    $old = filterShelfItem('Read long ago');
    $recent = filterShelfItem('Read this week');
    $lastMonth = filterShelfItem('Read three weeks ago');

    $event = fn (LibraryItem $item, int $daysAgo, int $count) => collect(range(1, $count))->each(fn () => LibraryReadingEvent::query()->create([
        'user_id' => User::factory()->create()->id,
        'library_item_id' => $item->id,
        'page_number' => 1,
        'session_hash' => str_repeat('a', 16),
        'device_hash' => str_repeat('b', 16),
        'occurred_at' => now()->subDays($daysAgo),
    ]));
    $event($old, 60, 9);
    $event($recent, 2, 3);
    $event($lastMonth, 20, 5);

    expect(filterShelfTitles(['sort' => 'popular_week']))->toBe(['Read this week', 'Read three weeks ago', 'Read long ago'])
        ->and(filterShelfTitles(['sort' => 'popular_month']))->toBe(['Read three weeks ago', 'Read this week', 'Read long ago']);

    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['sort' => 'popular_week']))->assertOk()->assertSee('Popular this week');
});

it('finds peer-reviewed and open-access research, and nothing that is not research', function () {
    $reviewed = filterShelfItem('Reviewed paper', ['content_type' => 'research', 'access_type' => 'paid', 'price' => 50]);
    $open = filterShelfItem('Open paper', ['content_type' => 'research', 'access_type' => 'free_public']);
    filterShelfItem('Open article', ['content_type' => 'article', 'access_type' => 'free_public']);

    LibraryReviewAssignment::query()->create([
        'library_item_id' => $reviewed->id,
        'reviewer_user_id' => User::factory()->create()->id,
        'assigned_by' => User::factory()->create()->id,
        'status' => 'done',
        'recommendation' => 'accept',
    ]);
    // A reviewer who has not reported yet does not make it peer-reviewed.
    LibraryReviewAssignment::query()->create([
        'library_item_id' => $open->id,
        'reviewer_user_id' => User::factory()->create()->id,
        'assigned_by' => User::factory()->create()->id,
        'status' => 'assigned',
    ]);

    expect(filterShelfTitles(['peer_reviewed' => '1']))->toBe(['Reviewed paper'])
        ->and(filterShelfTitles(['open_access' => '1']))->toBe(['Open paper'])
        ->and(filterShelfTitles(['peer_reviewed' => '1', 'open_access' => '1']))->toBe([])
        ->and(filterShelfTitles(['peer_reviewed' => '0']))->toHaveCount(3);
});

it('lets the writer and the office say how hard and how long, and refuses a made-up level', function () {
    $writerUser = User::factory()->create();
    $application = app(\App\Domains\Library\Actions\ApplyAsWriterAction::class)->execute($writerUser->id, [
        'display_name' => 'Ustadha Aminath', 'agreement_accepted' => true,
    ]);
    app(\App\Domains\Library\Actions\DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), [
            'title' => 'Sun letters', 'content_type' => 'article', 'access_type' => 'free_public',
            'body' => '<p>x</p>', 'difficulty' => 'beginner', 'reading_time' => 7,
        ])
        ->assertSessionHasNoErrors();
    $item = LibraryItem::query()->where('title', 'Sun letters')->firstOrFail();
    expect($item->difficulty)->toBe('beginner')->and($item->reading_time)->toBe(7);

    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), ['title' => 'Moon letters', 'content_type' => 'article', 'access_type' => 'free_public', 'difficulty' => 'expert'])
        ->assertSessionHasErrors('difficulty');

    // A save that does not mention difficulty leaves it as it was.
    app(SaveLibraryItemAction::class)->execute(['title' => 'Sun letters', 'content_type' => 'article', 'access_type' => 'free_public', 'body' => '<p>y</p>'], $item);
    expect($item->fresh()->difficulty)->toBe('beginner');

    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $this->withoutLocalizationMiddleware()->actingAs($office->fresh())
        ->post(route('admin.library.items.store'), [
            'title' => 'Office handout', 'content_type' => 'course_material', 'access_type' => 'free_public', 'body' => '<p>h</p>', 'difficulty' => 'advanced',
        ])
        ->assertSessionHasNoErrors();
    expect(LibraryItem::query()->where('title', 'Office handout')->value('difficulty'))->toBe('advanced');
});
