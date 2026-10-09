<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Enums\GuardianRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * A Pay button on an Inertia page reaches the bank (STATUS §5px).
 *
 * The family's fees page and the learner's course catalogue post their Pay
 * and Enroll buttons as Inertia visits — XHRs. The controllers answered with
 * `redirect()->away()` to the bank's payment page, and an XHR follows a 302 by
 * itself: across origins, to a page that does not answer for it, so the
 * browser refused and the button did nothing. The tests here passed all the
 * same, because they posted without the `X-Inertia` header a browser sends.
 * These send it.
 */
uses(RefreshDatabase::class);

const PAY1_BANK_PAGE = 'https://bank.example/pay/ABC123';

function fakeBankProvider(bool $succeeds = true): void
{
    app()->instance(PaymentProviderInterface::class, new class($succeeds) implements PaymentProviderInterface
    {
        public function __construct(private bool $succeeds) {}

        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return $this->succeeds
                ? new PaymentInitiationResult(true, PAY1_BANK_PAGE)
                : new PaymentInitiationResult(false, null, null, 'The bank is not answering.');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(false, null, null, 'failed', []);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

/** A parent with one child on a class roster and one unpaid fee invoice. */
function familyWithAFee(): array
{
    Role::findOrCreate('parent', 'web');
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent(['first_name' => 'Aishath', 'last_name' => 'Naseem']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Mother, true);
    $parent = User::query()->findOrFail($guardian->user_id);
    $parent->assignRole('parent');

    return [$parent, makeSchoolInvoice((int) $parent->id, (int) $student->id, (int) $year->id, 750)];
}

/** What a browser sends with an Inertia visit. */
function inertiaVisit(): array
{
    return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];
}

it('sends a family’s Pay to the bank as a full browser visit', function () {
    [$parent, $invoice] = familyWithAFee();
    fakeBankProvider();

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.invoices.pay', $invoice->id), ['mode' => 'full'], inertiaVisit())
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', PAY1_BANK_PAGE);

    // The payment waits for the bank's webhook, as before (rule 12).
    expect(Payment::query()->where('payable_type', 'invoice')->where('payable_id', $invoice->id)->value('status'))->toBe('initiated');

    // A plain form post still gets the redirect it always did.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.invoices.pay', $invoice->id), ['mode' => 'full'])
        ->assertRedirect(PAY1_BANK_PAGE);
});

it('sends a learner’s Enroll on a paid course to the bank as a full browser visit', function () {
    $author = actingPeopleAdmin(['courses.manage']);
    $learner = User::factory()->create();
    makeStudent(['user_id' => $learner->id]);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Paid Tajweed Course',
        'subject_id' => CourseSubject::query()->where('slug', 'tajweed')->value('id'),
        'created_by' => $author->id,
    ]);
    $course->registration_fee_amount = 100;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    fakeBankProvider();

    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('learn.courses.enroll', $course->id), [], inertiaVisit())
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', PAY1_BANK_PAGE);
});

it('leaves a redirect within the site as a redirect for an Inertia visit', function () {
    [$parent, $invoice] = familyWithAFee();
    fakeBankProvider(succeeds: false);

    // The bank refused: back to the fees page with the reason, in place.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.invoices.pay', $invoice->id), ['mode' => 'full'], inertiaVisit())
        ->assertRedirect(route('portal.invoices'))
        ->assertSessionHas('error', 'The bank is not answering.');
});
