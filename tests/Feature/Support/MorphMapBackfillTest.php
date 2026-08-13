<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('rewrites legacy model_has_roles so hasRole works after backfill', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('admin', 'web');

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => 'App\\Models\\User',
        'model_id' => $user->id,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($user->fresh()->hasRole('admin'))->toBeFalse();

    $updated = MorphMap::backfill();
    expect($updated['model_has_roles.model_type'])->toBeGreaterThan(0);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe('user')
        ->and($user->fresh()->hasRole('admin'))->toBeTrue();
});

it('rewrites legacy payments.payable_type so payable resolves', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();

    $paymentId = DB::table('payments')->insertGetId([
        'user_id' => $user->id,
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'TEST-'.Str::uuid(),
        'payable_type' => 'App\\Models\\Course',
        'payable_id' => $course->id,
        'amount_mvr' => 100,
        'amount_laar' => 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    MorphMap::backfill();

    $payment = Payment::query()->findOrFail($paymentId);

    expect(DB::table('payments')->where('id', $paymentId)->value('payable_type'))->toBe('course')
        ->and($payment->payable)->toBeInstanceOf(Course::class)
        ->and($payment->payable->is($course))->toBeTrue();
});

it('rewrites legacy notifications.type and notifiable_type', function () {
    $user = User::factory()->create();
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\OtpEmailNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $user->id,
        'data' => json_encode(['code' => '123456']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    MorphMap::backfill();

    $row = DB::table('notifications')->where('id', $id)->first();

    expect($row->type)->toBe('App\\Domains\\Notifications\\Notifications\\OtpEmailNotification')
        ->and($row->notifiable_type)->toBe('user');
});

it('is idempotent — running backfill twice changes nothing on the second pass', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('teacher', 'web');

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => 'App\\Models\\User',
        'model_id' => $user->id,
    ]);

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\NewContactMessage',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $user->id,
        'data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $first = MorphMap::backfill();
    expect($first['model_has_roles.model_type'])->toBeGreaterThan(0)
        ->and($first['notifications.type'])->toBeGreaterThan(0)
        ->and($first['notifications.notifiable_type'])->toBeGreaterThan(0);

    $second = MorphMap::backfill();
    expect(array_sum($second))->toBe(0);

    $report = MorphMap::remainingLegacy();
    foreach ($report as $info) {
        expect($info['count'])->toBe(0);
    }
});

it('leaves null payments.payable_type untouched', function () {
    $user = User::factory()->create();

    $paymentId = DB::table('payments')->insertGetId([
        'user_id' => $user->id,
        'amount' => 50,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'merchant_reference' => 'NULL-PAY-'.Str::uuid(),
        'payable_type' => null,
        'payable_id' => null,
        'amount_mvr' => 50,
        'amount_laar' => 5000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    MorphMap::backfill();

    expect(DB::table('payments')->where('id', $paymentId)->value('payable_type'))->toBeNull();
});
