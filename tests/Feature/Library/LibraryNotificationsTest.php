<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\DetectLibraryReadingAbuseAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\RecordLibraryReadingEventAction;
use App\Domains\Library\Actions\RemindReadersToContinueAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Models\LibraryReadingProgress;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B11 (LIBRARY_PLAN §41, STATUS §5iu): the three notices the data supports —
 * a writer you have read published something new; pick a book back up; the
 * office hears of a reading alert.
 */
function notifiedTitles(User $user): array
{
    return UserNotification::query()->where('user_id', $user->id)->orderBy('id')->pluck('title')->all();
}

function b11Writer(string $name): User
{
    $user = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => $name, 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return $user;
}

function b11Publish(User $writer, string $title)
{
    $item = app(SaveWriterItemAction::class)->execute($writer->id, ['title' => $title, 'content_type' => 'article', 'access_type' => 'free_public', 'body' => '<p>'.$title.'</p>']);

    return app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id)->fresh();
}

it('tells the readers of a writer when that writer publishes something new, and nobody else', function () {
    $aminath = b11Writer('Ustadha Aminath');
    $first = b11Publish($aminath, 'Sun letters');
    $other = b11Writer('Somebody Else');
    $theirs = b11Publish($other, 'Not hers');

    $reader = User::factory()->create();
    $buyer = User::factory()->create();
    $stranger = User::factory()->create();
    LibraryReadingProgress::query()->create(['user_id' => $reader->id, 'library_item_id' => $first->id, 'current_page' => 1, 'progress_percent' => 10, 'last_read_at' => now()]);
    \App\Domains\Library\Models\LibraryPurchase::query()->create(['user_id' => $buyer->id, 'library_item_id' => $first->id, 'amount' => 20, 'currency' => 'MVR', 'status' => 'paid', 'purchased_at' => now()]);
    LibraryReadingProgress::query()->create(['user_id' => $stranger->id, 'library_item_id' => $theirs->id, 'current_page' => 1, 'progress_percent' => 10, 'last_read_at' => now()]);

    $second = b11Publish($aminath, 'Moon letters');

    expect(notifiedTitles($reader))->toBe(['New from Ustadha Aminath'])
        ->and(notifiedTitles($buyer))->toBe(['New from Ustadha Aminath'])
        ->and(notifiedTitles($stranger))->toBe([])
        ->and(UserNotification::query()->where('user_id', $reader->id)->first()->data['href'])->toBe('/library/'.$second->slug)
        // The writer hears "Published" (twice, once per work), not their own "New from".
        ->and(array_count_values(notifiedTitles($aminath))['Published'] ?? 0)->toBe(2)
        ->and(notifiedTitles($aminath))->not->toContain('New from Ustadha Aminath');

    // The first work had no earlier readers: nobody was told then.
    expect(UserNotification::query()->where('title', 'New from Ustadha Aminath')->count())->toBe(2);
});

it('reminds a reader who left a book a week ago, once, with the page — and not a finished, fresh or stale one', function () {
    $writer = b11Writer('Ustadha Aminath');
    $book = b11Publish($writer, 'Sun letters');
    $row = fn (int $daysAgo, array $extra = []) => LibraryReadingProgress::query()->create(array_merge([
        'user_id' => User::factory()->create()->id, 'library_item_id' => $book->id, 'current_page' => 4, 'progress_percent' => 40, 'last_read_at' => now()->subDays($daysAgo),
    ], $extra));

    $idle = $row(10);
    $fresh = $row(2);
    $stale = $row(45);
    $done = $row(10, ['completed_at' => now()->subDays(9)]);
    $recentlyReminded = $row(12, ['reminded_at' => now()->subDays(3)]);
    $longAgoReminded = $row(12, ['reminded_at' => now()->subDays(20)]);

    expect(app(RemindReadersToContinueAction::class)->execute())->toBe(2);
    foreach ([$idle, $longAgoReminded] as $reminded) {
        $note = UserNotification::query()->where('user_id', $reminded->user_id)->firstOrFail();
        expect($note->title)->toBe('Pick it back up?')
            ->and($note->message)->toContain('page 4 of "Sun letters"')
            ->and($note->data['href'])->toBe('/library/'.$book->slug.'/read?page=4')
            ->and($reminded->fresh()->reminded_at)->not->toBeNull();
    }
    foreach ([$fresh, $stale, $done, $recentlyReminded] as $left) {
        expect(UserNotification::query()->where('user_id', $left->user_id)->count())->toBe(0);
    }

    // Tomorrow: nothing new to say.
    expect(app(RemindReadersToContinueAction::class)->execute())->toBe(0);
    $this->artisan('library:remind-readers')->expectsOutputToContain('reminders sent: 0')->assertSuccessful();
});

it('tells the office once when a reading alert is raised', function () {
    config(['library.abuse.rapid_pages_threshold' => 3, 'library.abuse.rapid_pages_window_seconds' => 600]);
    $office = actingSystemAdmin(['library.manage']);
    $writer = b11Writer('Ustadha Aminath');
    $book = b11Publish($writer, 'Sun letters');
    $reader = User::factory()->create();

    for ($page = 1; $page <= 6; $page++) {
        // One session, one device: only the rapid-pages signal should fire.
        app(RecordLibraryReadingEventAction::class)->execute($reader->id, $book->id, $page, 'one-session', '10.0.0.1', 'UA');
        app(DetectLibraryReadingAbuseAction::class)->execute($reader->id, $book->id);
    }

    $notes = UserNotification::query()->where('user_id', $office->id)->where('title', 'Reading alert')->get();
    expect($notes)->toHaveCount(1)
        ->and($notes->first()->data['href'])->toBe('/admin/library/reading-alerts')
        ->and($notes->first()->message)->toContain('rapid pages');
});
