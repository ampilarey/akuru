<?php

use App\Domains\People\Actions\UnifyStudentsAction;
use App\Domains\People\Support\RepresentativeUnificationGate;
use Database\Seeders\UnificationRepresentativeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('maps the representative dataset with A2 unusable and contradiction counts and does not attach the wrong student', function () {
    $this->seed(UnificationRepresentativeSeeder::class);

    $report = app(UnifyStudentsAction::class)->execute();
    RepresentativeUnificationGate::fillPaymentUnifiedIds();
    $manifest = RepresentativeUnificationGate::requireManifest();
    $gate = RepresentativeUnificationGate::evaluate($report, $manifest);
    $paymentFailures = RepresentativeUnificationGate::paymentWrongStudentFailures($manifest);

    expect($report->matcher['national_id_unusable_skips'])->toBeGreaterThanOrEqual(8)
        ->and($report->matcher['national_id_contradiction_fallthroughs'])->toBe(1)
        ->and($report->mapped['national_id'])->toBeGreaterThanOrEqual(3)
        ->and($report->mapped['name_dob'])->toBeGreaterThanOrEqual(6)
        ->and($gate['ok'])->toBeTrue()
        ->and($gate['failures'])->toBeEmpty()
        ->and($paymentFailures)->toBeEmpty();

    $fatima = $manifest['scenarios']['duplicate_nid_fatima'];
    $hussain = $manifest['scenarios']['duplicate_nid_hussain'];
    expect((int) DB::table('students')->where('legacy_registration_student_id', $fatima['registration_student_id'])->value('id'))
        ->toBe((int) $fatima['student_id'])
        ->and((int) DB::table('students')->where('legacy_registration_student_id', $hussain['registration_student_id'])->value('id'))
        ->toBe((int) $hussain['student_id'])
        ->and((int) $fatima['student_id'])->not->toBe((int) $hussain['student_id']);

    $older = $manifest['scenarios']['same_name_older'];
    $younger = $manifest['scenarios']['same_name_younger'];
    expect((int) DB::table('students')->where('legacy_registration_student_id', $older['registration_student_id'])->value('id'))
        ->toBe((int) $older['student_id'])
        ->and((int) DB::table('students')->where('legacy_registration_student_id', $younger['registration_student_id'])->value('id'))
        ->toBe((int) $younger['student_id']);

    $wrongId = (int) $manifest['scenarios']['nid_contradiction_wrong_student_id'];
    expect(DB::table('students')->where('id', $wrongId)->value('legacy_registration_student_id'))->toBeNull();

    foreach ($manifest['expected_unresolved_registration_student_ids'] as $rsId) {
        expect(DB::table('students')->where('legacy_registration_student_id', $rsId)->count())->toBe(0)
            ->and(DB::table('course_enrollments')->where('student_id', $rsId)->value('unified_student_id'))->toBeNull()
            ->and(DB::table('payments')->where('student_id', $rsId)->value('unified_student_id'))->toBeNull();
    }
});

it('passes students:verify-unification --representative on the seeded dataset', function () {
    $this->artisan('students:verify-unification', ['--representative' => true])
        ->expectsOutputToContain('matcher: national_id_unusable_skips=')
        ->expectsOutputToContain('students:verify-unification OK — representative dataset')
        ->assertSuccessful();
});
