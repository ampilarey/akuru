<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/buy.mjs` has the student buy `SMOKE-Wallet-Course` with
 * the `SMOKE-OFF` coupon and their wallet. `SmokeMarkerSeeder::buyCycle()`
 * keeps the course and its lesson, clears the enrolment and the coupon,
 * and tops the wallet back up — so the walk can run twice, and the seeder
 * must too.
 */
it('keeps the priced course with one published lesson, clears the coupon and enrolment, and funds the wallet', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $courseId = (int) DB::table('courses')->where('slug', 'smoke-wallet-course')->value('id');
    $studentUserId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');

    expect($courseId)->toBeGreaterThan(0)
        ->and((float) DB::table('courses')->where('id', $courseId)->value('registration_fee_amount'))->toBe(100.0)
        ->and((bool) DB::table('courses')->where('id', $courseId)->value('requires_admin_approval'))->toBeFalse()
        ->and(DB::table('lessons')->where('course_id', $courseId)->whereNotNull('current_revision_id')->count())->toBe(1)
        ->and((float) DB::table('wallets')->where('user_id', $studentUserId)->value('balance'))->toBeGreaterThanOrEqual(500.0);

    // What a run leaves behind.
    $codeId = DB::table('discount_codes')->insertGetId([
        'code' => 'SMOKE-OFF', 'name' => 'left over', 'discount_type' => 'percentage', 'discount_value' => 10,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $enrollmentId = DB::table('course_enrollments')->insertGetId([
        'course_id' => $courseId,
        'student_id' => (int) DB::table('registration_students')->orderBy('id')->value('id'),
        'unified_student_id' => (int) DB::table('students')->where('user_id', $studentUserId)->value('id'),
        'status' => 'active', 'payment_status' => 'confirmed', 'enrolled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('discount_redemptions')->insert([
        'discount_code_id' => $codeId, 'user_id' => $studentUserId, 'purchase_type' => 'course_enrollment', 'purchase_id' => $enrollmentId,
        'amount_discounted' => 10, 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('discount_redemptions')->where('discount_code_id', $codeId)->exists())->toBeFalse()
        ->and(DB::table('discount_codes')->where('id', $codeId)->exists())->toBeFalse()
        ->and(DB::table('course_enrollments')->where('id', $enrollmentId)->exists())->toBeFalse()
        ->and(DB::table('lessons')->where('course_id', $courseId)->whereNotNull('current_revision_id')->count())->toBe(1);
});
