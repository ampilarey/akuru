<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Contracts\InvalidDeviceTokenException;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Models\Device;
use App\Domains\Notifications\Services\FcmPushSender;
use App\Domains\Notifications\Services\LogPushSender;
use App\Domains\Notifications\Services\NullPushSender;
use App\Domains\Notifications\Support\PushChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * SPEC §50 push notifications (STATUS §5jr): the mobile app registers the
 * phone it runs on, a person sees and removes their phones, every in-app
 * notification fans out to registered phones when a sender is configured,
 * and the FCM sender speaks HTTP v1 and retires dead tokens — all behind
 * `PushSenderInterface` (rule 4), off by default.
 */
function fakePush(bool $succeeds = true): object
{
    $sender = new class($succeeds) implements PushSenderInterface
    {
        public array $sent = [];

        public function __construct(private bool $succeeds) {}

        public function sendToDevice(string $deviceToken, array $payload): bool
        {
            $this->sent[] = ['token' => $deviceToken, 'payload' => $payload];

            return $this->succeeds;
        }
    };
    app()->instance(PushSenderInterface::class, $sender);

    return $sender;
}

it('registers, refreshes and re-homes a phone by its token, and forgets it at sign-out', function () {
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($user)->postJson(route('account.devices.store'), [
        'token' => 'fcm-token-abcdef', 'platform' => 'android', 'device_name' => 'Pixel 7', 'locale' => 'dv',
    ])->assertOk()->assertJson(['ok' => true]);
    $device = Device::query()->where('token', 'fcm-token-abcdef')->sole();
    expect($device->user_id)->toBe($user->id)->and($device->platform)->toBe('android')->and($device->locale)->toBe('dv')->and($device->is_active)->toBeTrue()->and($device->last_seen_at)->not->toBeNull();

    // Seen again from the same phone: one row, refreshed, not a duplicate.
    $this->withoutLocalizationMiddleware()->actingAs($user)->postJson(route('account.devices.store'), ['token' => 'fcm-token-abcdef', 'platform' => 'android', 'app_version' => '1.2.0'])->assertOk();
    expect(Device::query()->where('token', 'fcm-token-abcdef')->count())->toBe(1)->and($device->fresh()->app_version)->toBe('1.2.0');

    // The phone changes hands: the token moves to whoever holds it now.
    $other = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($other)->postJson(route('account.devices.store'), ['token' => 'fcm-token-abcdef', 'platform' => 'android'])->assertOk();
    expect($device->fresh()->user_id)->toBe($other->id);

    // Sign-out forgets it; somebody else cannot.
    $this->withoutLocalizationMiddleware()->actingAs($user)->postJson(route('account.devices.forget'), ['token' => 'fcm-token-abcdef'])->assertOk()->assertJson(['ok' => false]);
    expect($device->fresh()->is_active)->toBeTrue();
    $this->withoutLocalizationMiddleware()->actingAs($other)->postJson(route('account.devices.forget'), ['token' => 'fcm-token-abcdef'])->assertOk()->assertJson(['ok' => true]);
    expect($device->fresh()->is_active)->toBeFalse();

    // Validation.
    $this->withoutLocalizationMiddleware()->actingAs($user)->postJson(route('account.devices.store'), ['token' => 'short', 'platform' => 'watch'])->assertStatus(422);
});

it('refuses a guest', function () {
    $this->withoutLocalizationMiddleware()->postJson(route('account.devices.store'), ['token' => 'fcm-token-abcdef'])->assertStatus(401);
    $this->withoutLocalizationMiddleware()->postJson(route('account.devices.forget'), ['token' => 'fcm-token-abcdef'])->assertStatus(401);
});

it('lists a person’s phones on the notification centre and lets them remove one, never somebody else’s', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Device::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'token' => 'apns-mine', 'device_name' => 'iPhone 15', 'is_active' => true, 'last_seen_at' => now()]);
    $theirs = Device::query()->create(['user_id' => $other->id, 'platform' => 'android', 'token' => 'fcm-theirs', 'is_active' => true]);

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('portal.notifications'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Notifications')->has('devices', 1)
            ->where('devices.0.id', $mine->id)->where('devices.0.platform', 'ios')->where('devices.0.name', 'iPhone 15')->where('devices.0.active', true)
            ->where('t.devices_title', 'Your phones')->where('t.devices_platform_ios', 'iPhone'));

    $this->withoutLocalizationMiddleware()->actingAs($user)->delete(route('account.devices.destroy', $theirs))->assertRedirect();
    expect(Device::query()->whereKey($theirs->id)->exists())->toBeTrue();
    $this->withoutLocalizationMiddleware()->actingAs($user)->delete(route('account.devices.destroy', $mine))->assertRedirect()->assertSessionHas('success', 'That phone no longer receives notifications.');
    expect(Device::query()->whereKey($mine->id)->exists())->toBeFalse();

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['devices_title', 'devices_hint', 'devices_none', 'devices_flash_registered'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});

it('fans an in-app notification out to the person’s active phones only when a sender is configured', function () {
    $user = User::factory()->create();
    Device::query()->create(['user_id' => $user->id, 'platform' => 'android', 'token' => 'live-token', 'is_active' => true]);
    Device::query()->create(['user_id' => $user->id, 'platform' => 'android', 'token' => 'old-token', 'is_active' => false]);

    // Off by default: the in-app row is written, nothing is pushed.
    config(['push.driver' => 'null']);
    $sender = fakePush();
    $row = app(SendUserNotificationAction::class)->execute($user->id, 'Register due', 'Please fill your register.', ['category' => 'registers', 'href' => '/academics/registers']);
    expect($row)->not->toBeNull()->and($sender->sent)->toBe([]);

    // Configured: the same notification reaches the live phone, with a place to open.
    config(['push.driver' => 'log']);
    $sender = fakePush();
    app(SendUserNotificationAction::class)->execute($user->id, 'Register due', 'Please fill your register.', ['category' => 'registers', 'href' => '/academics/registers']);
    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]['token'])->toBe('live-token')
        ->and($sender->sent[0]['payload']['title'])->toBe('Register due')
        ->and($sender->sent[0]['payload']['data']['url'])->toBe('/academics/registers')
        ->and($sender->sent[0]['payload']['data']['category'])->toBe('registers');

    // A dead token is retired, not retried forever.
    app()->instance(PushSenderInterface::class, new class implements PushSenderInterface
    {
        public function sendToDevice(string $deviceToken, array $payload): bool
        {
            throw new InvalidDeviceTokenException('UNREGISTERED');
        }
    });
    app(SendUserNotificationAction::class)->execute($user->id, 'Again', 'Body', ['category' => 'registers']);
    expect(Device::query()->where('token', 'live-token')->value('is_active'))->toBeFalse();
});

it('picks the sender from config and fails closed when FCM is half configured', function () {
    config(['push.driver' => 'null']);
    app()->forgetInstance(PushSenderInterface::class);
    expect(PushChannel::enabled())->toBeFalse()->and(app(PushSenderInterface::class))->toBeInstanceOf(NullPushSender::class);

    config(['push.driver' => 'log']);
    app()->forgetInstance(PushSenderInterface::class);
    expect(PushChannel::driver())->toBe('log')->and(app(PushSenderInterface::class))->toBeInstanceOf(LogPushSender::class);

    config(['push.driver' => 'fcm', 'push.fcm.project_id' => 'akuru-test', 'push.fcm.credentials' => '/nowhere/key.json']);
    app()->forgetInstance(PushSenderInterface::class);
    expect(PushChannel::driver())->toBe('null')->and(app(PushSenderInterface::class))->toBeInstanceOf(NullPushSender::class);

    $key = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($key, '{}');
    config(['push.fcm.credentials' => $key]);
    app()->forgetInstance(PushSenderInterface::class);
    expect(PushChannel::driver())->toBe('fcm')->and(app(PushSenderInterface::class))->toBeInstanceOf(FcmPushSender::class);
    unlink($key);
});

it('speaks FCM HTTP v1 with a signed service-account assertion and retires an unregistered token', function () {
    $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($pair, $pem);
    $key = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($key, json_encode(['client_email' => 'push@akuru-test.iam.gserviceaccount.com', 'private_key' => $pem]));

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/v1/projects/akuru-test/messages:send' => Http::sequence()
            ->push(['name' => 'projects/akuru-test/messages/1'])
            ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
    ]);

    $sender = new FcmPushSender('akuru-test', $key, 5);
    expect($sender->sendToDevice('device-token-1', ['title' => 'Register due', 'body' => 'Please fill it.', 'data' => ['url' => '/academics/registers', 'count' => 3]]))->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
            return false;
        }
        $assertion = $request['assertion'] ?? '';
        $claims = json_decode(base64_decode(strtr(explode('.', $assertion)[1] ?? '', '-_', '+/')), true);

        return ($request['grant_type'] ?? '') === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && ($claims['iss'] ?? '') === 'push@akuru-test.iam.gserviceaccount.com'
            && ($claims['scope'] ?? '') === 'https://www.googleapis.com/auth/firebase.messaging';
    });
    Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
        && $request->hasHeader('Authorization', 'Bearer ya29.test')
        && $request['message']['token'] === 'device-token-1'
        && $request['message']['notification']['title'] === 'Register due'
        && $request['message']['data']['url'] === '/academics/registers'
        && $request['message']['data']['count'] === '3');

    expect(fn () => $sender->sendToDevice('device-token-gone', ['title' => 'x']))->toThrow(InvalidDeviceTokenException::class);
    unlink($key);
});
