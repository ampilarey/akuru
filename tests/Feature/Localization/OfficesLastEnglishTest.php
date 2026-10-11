<?php

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Finance\Actions\RecordManualPaymentAction;
use App\Domains\HR\Actions\ApproveStaffLeaveAction;
use App\Domains\HR\Models\LeaveType;
use App\Domains\Settings\Actions\ListFeatureWalkthroughAction;
use App\Domains\Settings\Actions\RecordFeatureTestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * The offices' last English refusals, in Dhivehi and Arabic (BACKLOG C21,
 * slice OF1, STATUS §5rk).
 *
 * Deleting a course, a sign-in by phone to an inactive account, the feature
 * walk-through, staff leave, an ad-hoc invoice, a manual payment and a Hifz
 * milestone review each refused in English whatever the page's language. A
 * course's dependents were named by their table (*1 enrolments*) and the
 * refusal quoted the spec (*SPEC §29*).
 */
uses(RefreshDatabase::class);

function of1Files(): array
{
    return [
        'app/Domains/Courses/Actions/DeleteCourseAction.php',
        'app/Domains/Identity/Actions/PhoneSignInAction.php',
        'app/Domains/Settings/Actions/RecordFeatureTestAction.php',
        'app/Domains/HR/Actions/AssertLeaveDocumentAction.php',
        'app/Domains/HR/Actions/ApproveStaffLeaveAction.php',
        'app/Domains/Finance/Actions/RaiseAdHocInvoiceAction.php',
        'app/Domains/Finance/Actions/RecordManualPaymentAction.php',
        'app/Domains/Hifz/Actions/WriteHifzMilestonesAction.php',
    ];
}

/** @return array<string, list<string>> */
function of1Refusal(callable $call): array
{
    try {
        $call();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('leaves no refusal in English in these offices, and keys each in three languages', function () {
    foreach (of1Files() as $file) {
        expect(refusalEnglishIn($file))->toBe([], $file);
    }

    $keys = [
        'teach' => ['delete_error_has_records', 'delete_dependent_course_enrollments', 'delete_dependent_attendance_records', 'delete_dependent_activity_attempts', 'delete_dependent_assessment_attempts', 'delete_dependent_student_lesson_progress', 'delete_dependent_issued_certificates', 'delete_dependent_payment_items', 'delete_dependent_course_offerings'],
        'signin' => ['error_account_inactive'],
        'admin' => ['feature_test_error_unknown', 'feature_test_error_status', 'feature_test_error_comment', 'hifz_error_review_pending', 'hifz_error_already_decided'],
        'hr' => ['leave_error_document', 'leave_error_unknown_type', 'leave_error_dates', 'leave_error_no_year', 'leave_error_payroll_locked', 'leave_error_balance'],
        'finance' => ['invoice_error_amount', 'payment_error_method', 'payment_error_wallet_method'],
    ];
    foreach ($keys as $book => $phrases) {
        $en = require base_path("resources/lang/en/{$book}.php");
        $dv = require base_path("resources/lang/dv/{$book}.php");
        $ar = require base_path("resources/lang/ar/{$book}.php");
        foreach ($phrases as $phrase) {
            expect($en[$phrase] ?? null)->not->toBeNull("{$book}.{$phrase}")
                ->and($dv[$phrase] ?? '')->toMatch('/\p{Thaana}/u', "{$book}.{$phrase}")
                ->and($ar[$phrase] ?? '')->toMatch('/\p{Arabic}/u', "{$book}.{$phrase}");
            // Every placeholder the English names is kept in both translations.
            preg_match_all('/:([a-z_]+)/', $en[$phrase], $names);
            foreach ($names[1] as $name) {
                expect(str_contains($dv[$phrase], ":{$name}") && str_contains($ar[$phrase], ":{$name}"))->toBeTrue("{$book}.{$phrase} :{$name}");
            }
        }
    }
});

it('refuses a feature test, a manual payment and a leave in Dhivehi', function () {
    app()->setLocale('dv');
    $admin = actingPeopleAdmin(['settings.manage']);

    expect(of1Refusal(fn () => app(RecordFeatureTestAction::class)->execute('no-such-feature', 'works', null, $admin->id)))
        ->toBe(['item' => [__('admin.feature_test_error_unknown', [], 'dv')]]);
    $key = ListFeatureWalkthroughAction::itemKeys()[0];
    expect(of1Refusal(fn () => app(RecordFeatureTestAction::class)->execute($key, 'broken', ' ', $admin->id)))
        ->toBe(['comment' => [__('admin.feature_test_error_comment', [], 'dv')]]);

    expect(of1Refusal(fn () => app(RecordManualPaymentAction::class)->execute('invoice', 1, $admin->id, 10, null, $admin->id, 'barter')))
        ->toBe(['payment_method' => [__('finance.payment_error_method', [], 'dv')]]);

    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();
    $sick = LeaveType::query()->where('code', 'sick')->firstOrFail();
    $said = of1Refusal(fn () => app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id, 'leave_type_id' => $sick->id, 'from_date' => '2026-09-28', 'to_date' => '2026-09-28',
    ]));
    expect($said['document_id'][0] ?? implode(' ', array_merge(...array_values($said))))->toMatch('/\p{Thaana}/u')
        ->not->toContain('supporting document');

    expect(of1Refusal(fn () => app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id, 'leave_type_id' => 999999, 'from_date' => '2026-09-28',
    ])))->toBe(['leave_type_id' => [__('hr.leave_error_unknown_type', [], 'dv')]]);
});

it('names what keeps a course from being deleted, in Arabic, without its table or the spec', function () {
    app()->setLocale('ar');
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'OF1 Busy course',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'unified_student_id' => makeStudent()->id,
        'status' => 'active',
        'payment_status' => 'pending',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    $said = of1Refusal(fn () => app(DeleteCourseAction::class)->executeStrict($course))['course'][0] ?? '';
    expect($said)->toBe(__('teach.delete_error_has_records', ['records' => __('teach.delete_dependent_course_enrollments', ['count' => 1], 'ar')], 'ar'))
        ->not->toContain('enrolments')
        ->not->toContain('§29');
});
