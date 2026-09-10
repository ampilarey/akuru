<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\SendPushNotificationAction;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Models\Device;
use App\Domains\Notifications\Models\UserNotification;
use App\Domains\Notifications\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Push notifications told the truth about nothing.
 *
 * `NotificationService::sendPushNotification()` wrote a log line and returned,
 * after which the caller marked the notification **sent**. Every push was
 * recorded as delivered while nothing left the building — worse than an
 * unimplemented channel, because nobody goes looking for a message the system
 * says arrived.
 */
function fakePushSender(bool $succeeds): void
{
    app()->instance(PushSenderInterface::class, new class($succeeds) implements PushSenderInterface
    {
        public array $sent = [];

        public function __construct(private bool $succeeds) {}

        public function sendToDevice(string $deviceToken, array $payload): bool
        {
            $this->sent[] = $deviceToken;

            return $this->succeeds;
        }
    });
}

function pushNotification(User $user): UserNotification
{
    return UserNotification::query()->create([
        'user_id' => $user->id,
        'type' => 'push',
        'category' => 'general',
        'title' => 'Register due',
        'message' => 'Please fill your register.',
        'status' => 'pending',
    ]);
}

it('reports nothing delivered when no device is registered', function () {
    $user = User::factory()->create();

    expect(app(SendPushNotificationAction::class)->execute((int) $user->id, ['title' => 'x']))
        ->toBe(['devices' => 0, 'delivered' => 0]);
});

it('sends to every active device', function () {
    $user = User::factory()->create();
    foreach (['token-a', 'token-b'] as $token) {
        Device::query()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'token' => $token,
            'is_active' => true,
        ]);
    }
    fakePushSender(true);

    expect(app(SendPushNotificationAction::class)->execute((int) $user->id, ['title' => 'x']))
        ->toBe(['devices' => 2, 'delivered' => 2]);
});

it('skips a device that has been deactivated', function () {
    $user = User::factory()->create();
    Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'live', 'is_active' => true]);
    Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'dead', 'is_active' => false]);
    fakePushSender(true);

    expect(app(SendPushNotificationAction::class)->execute((int) $user->id, ['title' => 'x'])['devices'])
        ->toBe(1);
});

it('counts a person as reached when one of their devices takes it', function () {
    $user = User::factory()->create();
    Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'ok', 'is_active' => true]);
    Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'bad', 'is_active' => true]);

    app()->instance(PushSenderInterface::class, new class implements PushSenderInterface
    {
        public function sendToDevice(string $deviceToken, array $payload): bool
        {
            return $deviceToken === 'ok';
        }
    });

    // A dead tablet and a working phone is a person who has been reached.
    expect(app(SendPushNotificationAction::class)->execute((int) $user->id, ['title' => 'x']))
        ->toBe(['devices' => 2, 'delivered' => 1]);
});

it('does not send to somebody elses device', function () {
    $user = User::factory()->create();
    Device::query()->create([
        'user_id' => User::factory()->create()->id,
        'platform' => 'ios',
        'token' => 'not-theirs',
        'is_active' => true,
    ]);
    fakePushSender(true);

    expect(app(SendPushNotificationAction::class)->execute((int) $user->id, ['title' => 'x'])['devices'])
        ->toBe(0);
});

it('records a push as failed when there is no device to send it to', function () {
    $notification = pushNotification(User::factory()->create());

    app(NotificationService::class)->processNotification($notification);

    // The whole point: this is the normal path today, and it must not read as
    // success. Previously it was marked sent.
    expect($notification->refresh()->status)->toBe('failed')
        ->and($notification->error_message)->toContain('No active device');
});

it('records a push as failed when every device refuses it', function () {
    $user = User::factory()->create();
    Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'x', 'is_active' => true]);
    fakePushSender(false);
    $notification = pushNotification($user);

    app(NotificationService::class)->processNotification($notification);

    expect($notification->refresh()->status)->toBe('failed')
        ->and($notification->error_message)->toContain('Push delivery failed');
});

it('records a push as sent only when a device actually took it', function () {
    $user = User::factory()->create();
    Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'x', 'is_active' => true]);
    fakePushSender(true);
    $notification = pushNotification($user);

    app(NotificationService::class)->processNotification($notification);

    expect($notification->refresh()->status)->toBe('sent');
});
