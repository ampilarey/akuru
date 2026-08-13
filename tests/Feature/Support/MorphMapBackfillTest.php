<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    $result = MorphMap::backfill();
    expect($result['updated']['model_has_roles.model_type'])->toBeGreaterThan(0);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe('user')
        ->and($user->fresh()->hasRole('admin'))->toBeTrue();
});

it('rewrites post-Phase-0 domain FQCNs in morph columns to aliases', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $role = Role::findOrCreate('admin', 'web');

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
    ]);

    $paymentId = DB::table('payments')->insertGetId([
        'user_id' => $user->id,
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'DOMAIN-'.Str::uuid(),
        'payable_type' => Course::class,
        'payable_id' => $course->id,
        'amount_mvr' => 100,
        'amount_laar' => 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $notificationId = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Domains\\Notifications\\Notifications\\OtpEmailNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['code' => '999999']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(User::class)->toContain('\\')
        ->and(Course::class)->toContain('\\');

    $result = MorphMap::backfill();
    expect($result['updated']['model_has_roles.model_type'])->toBeGreaterThan(0)
        ->and($result['updated']['payments.payable_type'])->toBeGreaterThan(0)
        ->and($result['updated']['notifications.notifiable_type'])->toBeGreaterThan(0);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe('user')
        ->and(DB::table('payments')->where('id', $paymentId)->value('payable_type'))->toBe('course')
        ->and(DB::table('notifications')->where('id', $notificationId)->value('notifiable_type'))->toBe('user')
        ->and(Payment::query()->findOrFail($paymentId)->payable)->toBeInstanceOf(Course::class)
        ->and($user->fresh()->hasRole('admin'))->toBeTrue();
});

it('morph-map:verify fails when a morph column still holds an App\\Domains FQCN', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('admin', 'web');

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
    ]);

    $remaining = MorphMap::remainingLegacy();
    expect($remaining['model_has_roles.model_type']['count'])->toBeGreaterThan(0)
        ->and($remaining['model_has_roles.model_type']['values'])->toContain(User::class);

    $exit = Artisan::call('morph-map:verify');
    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('FAILED');
});

it('collapses duplicate role rows (legacy + domain FQCN) without PK violation', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('admin', 'web');

    DB::table('model_has_roles')->insert([
        [
            'role_id' => $role->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => $user->id,
        ],
        [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ],
    ]);

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(2);

    $result = MorphMap::backfill();

    expect($result['collapse_counts']['model_has_roles'] ?? 0)->toBe(1)
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1)
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($user->fresh()->hasRole('admin'))->toBeTrue();
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

it('is idempotent after mixed-era rewrites and collapses', function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate('teacher', 'web');

    DB::table('model_has_roles')->insert([
        [
            'role_id' => $role->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => $user->id,
        ],
        [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ],
    ]);

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\NewContactMessage',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $first = MorphMap::backfill();
    expect($first['updated']['model_has_roles.model_type'])->toBeGreaterThan(0)
        ->and($first['updated']['notifications.type'])->toBeGreaterThan(0)
        ->and($first['updated']['notifications.notifiable_type'])->toBeGreaterThan(0)
        ->and($first['collapse_counts']['model_has_roles'] ?? 0)->toBe(1);

    $second = MorphMap::backfill();
    expect(array_sum($second['updated']))->toBe(0)
        ->and($second['collapses'])->toBe([]);

    foreach (MorphMap::remainingLegacy() as $info) {
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
