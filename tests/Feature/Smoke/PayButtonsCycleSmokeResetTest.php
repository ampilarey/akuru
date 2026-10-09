<?php

use App\Domains\Finance\Models\Payment;
use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/pay-buttons.mjs` presses Pay on `SMOKE-INV-1` and Enroll on
 * `SMOKE-Pay-Course` against a stand-in bank (STATUS §5px). Neither payment
 * is confirmed, so a run leaves an initiated payment on each and a pending
 * enrolment on the course. `SmokeMarkerSeeder::payButtonsCycle()` clears
 * those so both buttons are there to press again — and only those: a
 * payment that went through is money, and stays.
 */
it('clears a run\'s unconfirmed payments and pending enrolment, keeps a confirmed payment, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $courseId = (int) DB::table('courses')->where('slug', 'smoke-pay-course')->value('id');
    $invoiceId = (int) DB::table('invoices')->where('invoice_number', 'SMOKE-INV-1')->value('id');
    $parentId = (int) DB::table('users')->where('email', 'parent@akuru.edu.mv')->value('id');
    $studentUserId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');
    $studentId = (int) DB::table('students')->where('user_id', $studentUserId)->value('id');
    expect($courseId)->toBeGreaterThan(0)
        ->and(DB::table('courses')->where('id', $courseId)->value('registration_fee_amount'))->toEqual(150)
        ->and($invoiceId)->toBeGreaterThan(0);

    // What a run leaves behind.
    $pay = fn (array $row) => Payment::query()->create([
        'currency' => 'MVR', 'provider' => 'bml', 'unified_student_id' => $studentId, ...$row,
    ])->id;
    $feePayment = $pay(['user_id' => $parentId, 'amount' => 200, 'status' => 'pending', 'payable_type' => 'invoice', 'payable_id' => $invoiceId]);
    $enrollmentId = DB::table('course_enrollments')->insertGetId([
        'course_id' => $courseId, 'unified_student_id' => $studentId, 'status' => 'pending', 'payment_status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $coursePayment = $pay(['user_id' => $studentUserId, 'amount' => 150, 'status' => 'initiated', 'course_id' => $courseId, 'payable_type' => 'course_enrollment', 'payable_id' => $enrollmentId]);
    DB::table('course_enrollments')->where('id', $enrollmentId)->update(['payment_id' => $coursePayment]);
    // Money that came in is not the walk's residue.
    $confirmed = $pay(['user_id' => $parentId, 'amount' => 100, 'status' => 'confirmed', 'payable_type' => 'invoice', 'payable_id' => $invoiceId]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('payments')->where('id', $feePayment)->exists())->toBeFalse()
        ->and(DB::table('payments')->where('id', $coursePayment)->exists())->toBeFalse()
        ->and(DB::table('course_enrollments')->where('id', $enrollmentId)->exists())->toBeFalse()
        ->and(DB::table('payments')->where('id', $confirmed)->exists())->toBeTrue()
        ->and(DB::table('courses')->where('slug', 'smoke-pay-course')->count())->toBe(1);
});
