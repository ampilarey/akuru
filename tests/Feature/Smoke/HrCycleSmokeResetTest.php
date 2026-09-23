<?php

use App\Domains\HR\Models\PayrollPeriod;
use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/hr.mjs` walks a staff member's month: check-in, leave,
 * appraisal, payroll. `SmokeMarkerSeeder::hrCycle()` plants what it needs and
 * removes what the last run left, so the walk can run twice.
 *
 * The second case is the one that bit: the seeder planted a payroll period
 * with a status the enum does not have, and the payroll screen answered 500
 * on every seeded database (STATUS §5fd). Every planted status must be one
 * the model can read back.
 */
it('plants the HR-cycle markers once and clears a run\'s residue', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $userId = (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');
    $profileId = (int) DB::table('staff_profiles')->where('user_id', $userId)->value('id');

    expect($profileId)->toBeGreaterThan(0)
        ->and(DB::table('documents')->where('title', 'SMOKE-Permit')->count())->toBe(1)
        ->and(DB::table('staff_contracts')->where('staff_profile_id', $profileId)->where('status', 'active')->count())->toBe(1)
        ->and(DB::table('settings')->where('key', 'hr.staff_self_checkin')->value('value'))->toBe('1');

    // What a run leaves behind.
    $requestId = DB::table('requests')->insertGetId([
        'type' => 'staff_leave', 'requester_id' => $userId, 'reason' => 'SMOKE-Leave', 'status' => 'approved',
        'payload' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $entitlementId = (int) DB::table('leave_entitlements')->where('staff_profile_id', $profileId)->value('id');
    DB::table('leave_ledger')->insert(['entitlement_id' => $entitlementId, 'request_id' => $requestId, 'days' => -1, 'reason' => 'taken', 'created_at' => now(), 'updated_at' => now()]);
    $periodId = DB::table('payroll_periods')->insertGetId(['year' => 2099, 'month' => 12, 'status' => 'locked', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('payslips')->insert(['payroll_period_id' => $periodId, 'staff_profile_id' => $profileId, 'status' => 'final', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('appraisal_cycles')->insert(['name' => 'SMOKE-Cycle', 'academic_year_id' => DB::table('academic_years')->value('id'), 'opens_at' => '2026-01-01', 'closes_at' => '2026-12-31', 'created_at' => now(), 'updated_at' => now()]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('requests')->where('reason', 'SMOKE-Leave')->exists())->toBeFalse()
        ->and(DB::table('leave_ledger')->where('request_id', $requestId)->exists())->toBeFalse()
        ->and(DB::table('payroll_periods')->where('year', 2099)->exists())->toBeFalse()
        ->and(DB::table('appraisal_cycles')->where('name', 'SMOKE-Cycle')->exists())->toBeFalse()
        ->and(DB::table('documents')->where('title', 'SMOKE-Permit')->count())->toBe(1);
});

it('plants only statuses the payroll model can read, so the payroll screen renders', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);

    // Every planted period casts without a ValueError…
    expect(PayrollPeriod::query()->get()->every(fn (PayrollPeriod $period) => $period->status !== null))->toBeTrue();

    // …and the screen that lists them answers with payroll on (the seeder
    // sets the setting; the config side is the host's flag), which is when
    // the period list is read and a bad status would throw.
    config()->set('payroll.enabled', true);
    $admin = actingPeopleAdmin(['hr.manage', 'payroll.run', 'payroll.approve']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.payroll.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('HR/Payroll/Index'));
});

/**
 * Staging's first seeder run, with no teacher@ yet, had made a "Smoke
 * Marker" profile out of admin@, and `StaffProfile::first()` kept finding
 * that one (STATUS §5fz). The seeder now plants the HR markers on teacher@'s
 * profile and renames its own residue so no name lookup finds it first.
 */
it('plants the HR markers on teacher@\'s profile and renames a stray Smoke Marker', function () {
    $this->seed();
    $adminId = (int) DB::table('users')->where('email', 'admin@akuru.edu.mv')->value('id');
    $teacherId = (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');
    DB::table('staff_profiles')->insert([
        'user_id' => $adminId, 'first_name' => 'Smoke', 'last_name' => 'Marker', 'gender' => 'male',
        'joined_date' => '2026-01-01', 'employment_type' => 'full_time', 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    $teacherProfileId = (int) DB::table('staff_profiles')->where('user_id', $teacherId)->value('id');

    expect(DB::table('staff_profiles')->where('user_id', $adminId)->value('last_name'))->toBe('Stand-in')
        ->and(DB::table('staff_profiles')->where('first_name', 'Smoke')->where('last_name', 'Marker')->count())->toBe(1)
        ->and(DB::table('staff_attendance')->where('remarks', 'SMOKE-Attendance')->value('staff_profile_id'))->toBe($teacherProfileId)
        ->and(DB::table('staff_contracts')->where('staff_profile_id', $teacherProfileId)->where('status', 'active')->count())->toBe(1)
        ->and(DB::table('staff_contracts')->where('staff_profile_id', $teacherProfileId)->where('basic_salary', 12345)->exists())->toBeTrue();
});
