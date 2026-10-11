<?php

use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\PrayerTimes\Actions\ConfirmPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\PreviewPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\SavePrayerBroadcastAction;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastRecipientStatus;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerBroadcastRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * A sent prayer broadcast is locked, and the broadcast's refusals are in the
 * page's language (BACKLOG C21, slice PB1, STATUS §5rl).
 *
 * The broadcast's four refusals were English on every page, and previewing a
 * cancelled one was a 500 page. Worse, Save and Preview took any broadcast:
 * saving a sent one made it a draft again, previewing it deleted its
 * recipients — the record of who was sent what — and made them pending, and
 * Confirm then sent the same SMS a second time.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

/** @return array{admin: mixed, broadcast: PrayerBroadcast} */
function pb1SentBroadcast(): array
{
    seedPrayerTimesFixture();
    $admin = actingSystemAdmin(['prayer.manage']);
    $user = makePrayerContactUser('+9607772434');
    $student = makeStudent(['user_id' => $user->id]);
    app(RecordConsentAction::class)->execute('student', $student->id, 'prayer_reminders', true, $admin->id, 'admin');

    $broadcast = app(SavePrayerBroadcastAction::class)->execute([
        'mode' => 'daily', 'island_id' => 1, 'date_from' => '2025-01-10', 'language' => 'en',
        'recipient_refs' => [['type' => 'user', 'id' => $user->id]],
    ], null, $admin->id);
    app(PreviewPrayerBroadcastAction::class)->execute($broadcast);
    app(ConfirmPrayerBroadcastAction::class)->execute($broadcast->fresh(), $admin->id);

    return ['admin' => $admin, 'broadcast' => $broadcast->fresh()];
}

/** @return array<string, list<string>> */
function pb1Refusal(callable $call): array
{
    try {
        $call();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('leaves no broadcast refusal in English, and keys each in three languages', function () {
    foreach (['ConfirmPrayerBroadcastAction', 'PreviewPrayerBroadcastAction', 'SavePrayerBroadcastAction'] as $action) {
        expect(refusalEnglishIn("app/Domains/PrayerTimes/Actions/{$action}.php"))->toBe([], $action);
    }
    foreach (['prayer_error_not_previewed', 'prayer_error_no_recipients', 'prayer_error_locked', 'prayer_needs_split'] as $phrase) {
        expect((require base_path('resources/lang/en/admin.php'))[$phrase] ?? null)->not->toBeNull($phrase)
            ->and((require base_path('resources/lang/dv/admin.php'))[$phrase] ?? '')->toMatch('/\p{Thaana}/u', $phrase)
            ->and((require base_path('resources/lang/ar/admin.php'))[$phrase] ?? '')->toMatch('/\p{Arabic}/u', $phrase);
    }
});

it('never sends a sent broadcast again, and keeps its send record', function () {
    ['admin' => $admin, 'broadcast' => $broadcast] = pb1SentBroadcast();
    $sender = app(SmsSenderInterface::class);
    expect($sender->sent)->toHaveCount(1)
        ->and($broadcast->status)->toBe(PrayerBroadcastStatus::Completed);
    $sentRow = PrayerBroadcastRecipient::query()->where('prayer_broadcast_id', $broadcast->id)->sole();
    expect($sentRow->status)->toBe(PrayerBroadcastRecipientStatus::Sent);

    app()->setLocale('dv');
    $locked = ['broadcast' => [__('admin.prayer_error_locked', [], 'dv')]];
    expect(pb1Refusal(fn () => app(SavePrayerBroadcastAction::class)->execute(['mode' => 'daily', 'island_id' => 1, 'language' => 'en'], $broadcast, $admin->id)))->toBe($locked)
        ->and(pb1Refusal(fn () => app(PreviewPrayerBroadcastAction::class)->execute($broadcast)))->toBe($locked)
        ->and(pb1Refusal(fn () => app(ConfirmPrayerBroadcastAction::class)->execute($broadcast, $admin->id)))->toBe($locked);

    expect($sender->sent)->toHaveCount(1)
        ->and($broadcast->fresh()->status)->toBe(PrayerBroadcastStatus::Completed)
        ->and(PrayerBroadcastRecipient::query()->where('prayer_broadcast_id', $broadcast->id)->sole()->is($sentRow))->toBeTrue();
});

it('says a sent broadcast is locked from an Arabic page, and the page shows no Preview', function () {
    ['broadcast' => $broadcast] = pb1SentBroadcast();
    $office = actingSystemAdmin(['prayer.manage']);
    app()->setLocale('ar');
    $edit = route('admin.prayer-times.broadcasts.edit', $broadcast);

    $this->withoutLocalizationMiddleware()->actingAs($office)->from($edit)
        ->post(route('admin.prayer-times.broadcasts.preview', $broadcast))
        ->assertRedirect($edit)
        ->assertSessionHasErrors(['broadcast' => __('admin.prayer_error_locked', [], 'ar')]);
    $this->withoutLocalizationMiddleware()->actingAs($office)->from($edit)
        ->put(route('admin.prayer-times.broadcasts.update', $broadcast), ['mode' => 'daily', 'island_id' => 1, 'language' => 'en'])
        ->assertSessionHasErrors(['broadcast' => __('admin.prayer_error_locked', [], 'ar')]);

    expect($broadcast->fresh()->status)->toBe(PrayerBroadcastStatus::Completed);
    $page = (string) file_get_contents(resource_path('js/Pages/PrayerTimes/BroadcastForm.jsx'));
    expect($page)->toContain("!['draft', 'previewed'].includes(broadcast.status)")
        ->toContain('data-testid="broadcast-locked"');
});
