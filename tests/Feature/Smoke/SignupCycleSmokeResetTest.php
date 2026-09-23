<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/signup.mjs` builds `SMOKE-Trip` with a fee, has the pupil
 * answer it (which raises an invoice) and the parent confirm it.
 * `SmokeMarkerSeeder::signupCycle()` plants nothing and clears the invoice,
 * the answer and the sheet — so the walk can run twice, and the seeder must
 * too.
 */
it('clears a run\'s sheet, answer and the invoice its fee raised, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $userId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');
    $studentId = (int) DB::table('students')->where('user_id', $userId)->value('id');
    $yearId = (int) DB::table('academic_years')->orderBy('id')->value('id');

    // What a run leaves behind.
    $formId = DB::table('forms')->insertGetId([
        'created_by' => $userId, 'academic_year_id' => $yearId, 'title' => 'SMOKE-Trip',
        'fields' => json_encode([['key' => 'f1', 'label' => 'SMOKE-Q', 'type' => 'yes_no', 'options' => [], 'required' => true]]),
        'requires_parent_confirmation' => true, 'fee_amount' => 15, 'is_published' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $invoiceId = DB::table('invoices')->insertGetId([
        'invoice_number' => 'ADH-SMOKE-TRIP', 'student_id' => $studentId, 'academic_year_id' => $yearId, 'invoice_type' => 'other',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'status' => 'sent',
        'subtotal' => 15, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 15, 'paid_amount' => 0,
        'notes' => 'SMOKE-Trip', 'meta' => json_encode(['source' => 'form', 'source_id' => $formId]), 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('form_responses')->insert([
        'form_id' => $formId, 'user_id' => $userId, 'student_id' => $studentId, 'invoice_id' => $invoiceId, 'academic_year_id' => $yearId,
        'answers' => json_encode(['f1' => 'yes']), 'submitted_at' => now(), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('form_responses')->where('form_id', $formId)->exists())->toBeFalse()
        ->and(DB::table('invoices')->where('id', $invoiceId)->exists())->toBeFalse()
        ->and(DB::table('forms')->where('id', $formId)->exists())->toBeFalse();
});
