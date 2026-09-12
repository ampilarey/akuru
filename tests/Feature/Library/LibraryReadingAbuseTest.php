<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\DetectLibraryReadingAbuseAction;
use App\Domains\Library\Actions\RecordLibraryReadingEventAction;
use App\Domains\Library\Actions\ReviewLibraryReadingAlertAction;
use App\Domains\Library\Enums\LibraryAccessType;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryReadingSignal;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingAlert;
use App\Domains\Library\Models\LibraryReadingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * LIBRARY_PLAN §9.2 / §30.3 — the half of the protected reader L2 shipped
 * without: "detect rapid page opening, detect multi-device/session abuse,
 * limit simultaneous sessions, log suspicious activity".
 *
 * The first test is the one that matters most and is easiest to lose in a
 * refactor: **no raw IP or user agent may reach the database.** Some readers
 * here are children, and §30 is a security section rather than a surveillance
 * one — every question it asks is answerable from a hash.
 */
uses(RefreshDatabase::class);

function readingItem(string $slug = 'test-book'): LibraryItem
{
    return LibraryItem::query()->create([
        'title' => 'A Book',
        'slug' => $slug,
        // Read off the enums rather than guessed — an invented value inserts
        // cleanly and throws only when the model casts it back.
        'content_type' => LibraryContentType::Book->value,
        'access_type' => LibraryAccessType::FreeLogin->value,
        'status' => 'published',
        'language' => 'en',
    ]);
}

it('never stores a raw ip or user agent', function () {
    $user = User::factory()->create();
    $item = readingItem();

    app(RecordLibraryReadingEventAction::class)->execute(
        $user->id, $item->id, 1, 'session-abc', '203.0.113.9', 'Mozilla/5.0 (TestBrowser)'
    );

    $row = (array) DB::table('library_reading_events')->first();
    $serialised = json_encode($row);

    expect($serialised)->not->toContain('203.0.113.9')
        ->and($serialised)->not->toContain('TestBrowser')
        ->and($serialised)->not->toContain('session-abc')
        // …but the hashes are there, because that is what answers "same device?"
        ->and($row['device_hash'])->toHaveLength(64)
        ->and($row['session_hash'])->toHaveLength(64);
});

it('hashes the same device to the same value and different devices differently', function () {
    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);

    $record->execute($user->id, $item->id, 1, 's1', '10.0.0.1', 'Browser A');
    $record->execute($user->id, $item->id, 2, 's1', '10.0.0.1', 'Browser A');
    $record->execute($user->id, $item->id, 3, 's1', '10.0.0.2', 'Browser A');

    // Device identity is the pair: the same browser on a new address is a new
    // device row here, which is the conservative reading and keeps two people
    // behind one office NAT from looking like one device.
    expect(LibraryReadingEvent::query()->distinct()->count('device_hash'))->toBe(2);
});

it('does not log anonymous reading of free content', function () {
    $item = readingItem();

    $event = app(RecordLibraryReadingEventAction::class)->execute(null, $item->id, 1, 's1', '10.0.0.1', 'B');

    // An event with no reader answers none of §30.3's questions, and logging
    // anonymous visitors would be surveillance for its own sake.
    expect($event)->toBeNull()
        ->and(LibraryReadingEvent::query()->count())->toBe(0);
});

it('raises a rapid-pages alert past the configured threshold', function () {
    config()->set('library.abuse.rapid_pages_threshold', 5);
    config()->set('library.abuse.rapid_pages_window_seconds', 60);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    for ($i = 1; $i <= 6; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
    }

    $alerts = app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id);

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->signal)->toBe(LibraryReadingSignal::RapidPages)
        ->and($alerts[0]->observed)->toBe(6)
        ->and($alerts[0]->threshold)->toBe(5);
});

it('stays quiet for an ordinary reader', function () {
    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    for ($i = 1; $i <= 5; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
    }

    // A false positive accuses a paying reader of theft, so the quiet case is
    // asserted as deliberately as the noisy one.
    expect(app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id))->toBeEmpty()
        ->and(LibraryReadingAlert::query()->count())->toBe(0);
});

it('raises one alert for a burst, not one per page', function () {
    config()->set('library.abuse.rapid_pages_threshold', 3);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    $detect = app(DetectLibraryReadingAbuseAction::class);

    for ($i = 1; $i <= 12; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
        $detect->execute($user->id, $item->id);
    }

    // A reader who flips pages fast for ten minutes is one concern, not
    // hundreds of rows for somebody to wade through.
    $alerts = LibraryReadingAlert::query()->where('signal', LibraryReadingSignal::RapidPages->value)->get();
    expect($alerts)->toHaveCount(1)
        // …and the row keeps the worst reading, which is what a reviewer needs.
        ->and($alerts->first()->observed)->toBe(12);
});

it('flags too many devices on one account', function () {
    config()->set('library.abuse.device_threshold', 2);
    config()->set('library.abuse.rapid_pages_threshold', 999);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
        $record->execute($user->id, $item->id, 1, 's-'.$ip, $ip, 'Browser');
    }

    $signals = collect(app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id))
        ->map(fn ($a) => $a->signal);

    expect($signals)->toContain(LibraryReadingSignal::ManyDevices);
});

it('flags too many concurrent sessions', function () {
    config()->set('library.abuse.session_threshold', 2);
    config()->set('library.abuse.rapid_pages_threshold', 999);
    config()->set('library.abuse.device_threshold', 999);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    foreach (['a', 'b', 'c'] as $session) {
        $record->execute($user->id, $item->id, 1, $session, '10.0.0.1', 'Browser');
    }

    $signals = collect(app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id))
        ->map(fn ($a) => $a->signal);

    expect($signals)->toContain(LibraryReadingSignal::ConcurrentSessions);
});

it('ignores events outside the window', function () {
    config()->set('library.abuse.rapid_pages_threshold', 3);
    config()->set('library.abuse.rapid_pages_window_seconds', 60);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);

    $this->travelTo(now()->subHours(2));
    for ($i = 1; $i <= 10; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
    }
    $this->travelBack();

    // Yesterday's reading is not today's burst.
    expect(app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id))->toBeEmpty();
});

it('blocks nobody unless an operator turns enforcement on', function () {
    config()->set('library.abuse.session_threshold', 1);
    config()->set('library.abuse.enforce', false);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    foreach (['a', 'b', 'c'] as $session) {
        $record->execute($user->id, $item->id, 1, $session, '10.0.0.1', 'Browser');
    }

    $detector = app(DetectLibraryReadingAbuseAction::class);

    // Off by default: refusing a page locks a paying reader out of a book they
    // own, on a threshold nobody has checked against a real reader.
    expect($detector->shouldBlock($user->id))->toBeFalse();

    config()->set('library.abuse.enforce', true);
    expect($detector->shouldBlock($user->id))->toBeTrue();
});

it('closes an alert with a verdict and keeps the row', function () {
    config()->set('library.abuse.rapid_pages_threshold', 2);

    $reviewer = User::factory()->create();
    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    for ($i = 1; $i <= 3; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
    }
    $alert = app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id)[0];

    $reviewed = app(ReviewLibraryReadingAlertAction::class)->execute($alert, $reviewer->id, 'legitimate');

    expect($reviewed->outcome)->toBe('legitimate')
        ->and($reviewed->reviewed_by)->toBe($reviewer->id)
        // Kept, not deleted: an alert dismissed in September has to be
        // explainable in March, and a pattern of dismissals is worth seeing.
        ->and(LibraryReadingAlert::query()->count())->toBe(1);

    expect(fn () => app(ReviewLibraryReadingAlertAction::class)->execute($reviewed, $reviewer->id, 'abuse'))
        ->toThrow(ValidationException::class);
});

it('refuses an outcome it does not recognise', function () {
    config()->set('library.abuse.rapid_pages_threshold', 2);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);
    for ($i = 1; $i <= 3; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
    }
    $alert = app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id)[0];

    expect(fn () => app(ReviewLibraryReadingAlertAction::class)->execute($alert, $user->id, 'banned'))
        ->toThrow(ValidationException::class);
});

it('prunes events past the retention window but keeps alerts', function () {
    config()->set('library.abuse.retention_days', 30);
    config()->set('library.abuse.rapid_pages_threshold', 2);

    $user = User::factory()->create();
    $item = readingItem();
    $record = app(RecordLibraryReadingEventAction::class);

    $this->travelTo(now()->subDays(90));
    for ($i = 1; $i <= 3; $i++) {
        $record->execute($user->id, $item->id, $i, 's1', '10.0.0.1', 'B');
    }
    app(DetectLibraryReadingAbuseAction::class)->execute($user->id, $item->id);
    $this->travelBack();

    expect(LibraryReadingEvent::query()->count())->toBe(3)
        ->and(LibraryReadingAlert::query()->count())->toBe(1);

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    // The events go; the decision somebody took about them stays.
    expect(LibraryReadingEvent::query()->count())->toBe(0)
        ->and(LibraryReadingAlert::query()->count())->toBe(1);
});
