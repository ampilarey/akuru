<?php

use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * A discount code's slot comes back when a Library card purchase is abandoned,
 * and goes again when its payment lands late (STATUS §5pn).
 *
 * A reader who typed a code, chose the card and closed BML's page left a
 * `pending` purchase and a `pending` redemption. `ResolveDiscountAction`
 * counts pending redemptions against the code's limits, so that reader's one
 * use of a once-per-reader code was spent for good. A code limited to 100 uses
 * ran out after 100 attempts.
 *
 * Abandoned course enrolments gave their slot back in `akuru:prune-expired`,
 * and the Bookstore when a checkout expired. Nothing gave the Library's. And
 * when a payment landed after the prune had released its slot, the webhook
 * confirmed only `pending` redemptions, so the slot stayed free: one more use
 * of a once-per-reader code.
 */
uses(RefreshDatabase::class);

function abandonedLibraryItem(float $price = 80): LibraryItem
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Abandoned Book '.uniqueFixtureSuffix(),
        'content_type' => 'book',
        'access_type' => 'paid',
        'body' => '<p>Abandoned book page.</p>',
    ]);
    $item->price = $price;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

function abandonedOncePerReaderCode(string $code): void
{
    app(SaveDiscountCodeAction::class)->execute([
        'code' => $code,
        'discount_type' => 'fixed',
        'discount_value' => 10,
        'per_user_limit' => 1,
    ]);
}

/** A BML that takes every payment and confirms whatever its webhook names. */
function abandonedFakeBml(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/abandoned');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-ABANDONED',
                status: 'completed',
                rawPayload: $request->all(),
                isConfirmed: true,
            );
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function abandonedCardPurchase(User $reader, LibraryItem $item, string $code): LibraryPurchase
{
    test()->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.checkout', $item->slug), ['discount_code' => $code])
        ->assertRedirect('https://bml.test/pay/abandoned');

    return LibraryPurchase::query()->where('user_id', $reader->id)->latest('id')->firstOrFail();
}

function abandonedAge(string $table, int $id, int $hours): void
{
    Illuminate\Support\Facades\DB::table($table)->where('id', $id)->update(['created_at' => now()->subHours($hours)]);
}

function abandonedCodeIsFree(string $code, int $userId): bool
{
    try {
        app(ResolveDiscountAction::class)->execute($code, $userId, 100.0);

        return true;
    } catch (ValidationException) {
        return false;
    }
}

function abandonedConfirm(int $paymentId): void
{
    test()->postJson(url('/webhooks/bml'), [
        'reference' => Payment::query()->whereKey($paymentId)->value('merchant_reference'),
        'transactionId' => 'BML-ABANDONED',
        'status' => 'completed',
    ])->assertOk();
}

beforeEach(fn () => abandonedFakeBml());

it('gives the code back when a Library card purchase has waited a day unpaid', function () {
    $item = abandonedLibraryItem();
    abandonedOncePerReaderCode('LIBONCE');
    $reader = User::factory()->create();
    $purchase = abandonedCardPurchase($reader, $item, 'LIBONCE');

    expect(abandonedCodeIsFree('LIBONCE', $reader->id))->toBeFalse();

    abandonedAge('library_purchases', $purchase->id, 25);
    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect(DiscountRedemption::query()->sole()->status)->toBe('abandoned')
        ->and(abandonedCodeIsFree('LIBONCE', $reader->id))->toBeTrue()
        // The purchase moved no money and stays as it was, for a late payment to find.
        ->and($purchase->fresh()->status)->toBe('pending');
});

it('keeps the slot while the purchase is less than a day old', function () {
    $item = abandonedLibraryItem();
    abandonedOncePerReaderCode('LIBFRESH');
    $reader = User::factory()->create();
    $purchase = abandonedCardPurchase($reader, $item, 'LIBFRESH');

    abandonedAge('library_purchases', $purchase->id, 2);
    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect(DiscountRedemption::query()->sole()->status)->toBe('pending')
        ->and(abandonedCodeIsFree('LIBFRESH', $reader->id))->toBeFalse();
});

it('never gives back the slot of a purchase that was paid', function () {
    $item = abandonedLibraryItem();
    abandonedOncePerReaderCode('LIBPAID');
    $reader = User::factory()->create();
    $purchase = abandonedCardPurchase($reader, $item, 'LIBPAID');
    abandonedConfirm((int) $purchase->payment_id);

    abandonedAge('library_purchases', $purchase->id, 72);
    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect($purchase->fresh()->status)->toBe('paid')
        ->and(DiscountRedemption::query()->sole()->status)->toBe('confirmed')
        ->and(abandonedCodeIsFree('LIBPAID', $reader->id))->toBeFalse();
});

it('takes the slot back when the payment lands after the release', function () {
    $item = abandonedLibraryItem();
    abandonedOncePerReaderCode('LIBLATE');
    $reader = User::factory()->create();
    $purchase = abandonedCardPurchase($reader, $item, 'LIBLATE');
    abandonedAge('library_purchases', $purchase->id, 25);
    $this->artisan('akuru:prune-expired')->assertExitCode(0);
    expect(DiscountRedemption::query()->sole()->status)->toBe('abandoned');

    abandonedConfirm((int) $purchase->payment_id);

    expect($purchase->fresh()->status)->toBe('paid')
        ->and(LibraryAccessGrant::query()->where('user_id', $reader->id)->where('library_item_id', $item->id)->exists())->toBeTrue()
        ->and(DiscountRedemption::query()->sole()->status)->toBe('confirmed')
        ->and(abandonedCodeIsFree('LIBLATE', $reader->id))->toBeFalse();
});

it('releases nothing on a dry run', function () {
    $item = abandonedLibraryItem();
    abandonedOncePerReaderCode('LIBDRY');
    $reader = User::factory()->create();
    $purchase = abandonedCardPurchase($reader, $item, 'LIBDRY');
    abandonedAge('library_purchases', $purchase->id, 25);

    $this->artisan('akuru:prune-expired', ['--dry-run' => true])->assertExitCode(0);

    expect(DiscountRedemption::query()->sole()->status)->toBe('pending');
});

/** A BML that will not start a payment, as when the gateway is down or not configured. */
function abandonedRefusingBml(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(success: false, error: 'Gateway unavailable');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: false, merchantReference: null, providerReference: null, status: 'failed', rawPayload: [], isConfirmed: false);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

it('gives the code back at once, and says so, when the Library payment cannot start', function () {
    $item = abandonedLibraryItem();
    abandonedOncePerReaderCode('LIBDOWN');
    $reader = User::factory()->create();
    abandonedRefusingBml();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.checkout', $item->slug), ['discount_code' => 'LIBDOWN'])
        ->assertRedirect(route('public.library.show', $item->slug))
        ->assertSessionHas('error');

    expect(LibraryPurchase::query()->sole()->status)->toBe('failed')
        ->and(DiscountRedemption::query()->sole()->status)->toBe('abandoned')
        ->and(abandonedCodeIsFree('LIBDOWN', $reader->id))->toBeTrue();

    // My Library names the state in the page's language, not as a code.
    $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('payment did not start');
    app()->setLocale('dv');
    $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.library.my'))
        ->assertOk()
        ->assertSee(__('public.purchase_status_failed', [], 'dv'))
        ->assertDontSee('>failed<', false);
});

it('gives a course\'s code back at once when its payment cannot start', function () {
    [$learner, $course] = abandonedPaidCourse();
    abandonedOncePerReaderCode('COURSEDOWN');
    abandonedRefusingBml();

    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('learn.courses.enroll', $course->id), ['discount_code' => 'COURSEDOWN']);

    expect(DiscountRedemption::query()->sole()->status)->toBe('abandoned')
        ->and(abandonedCodeIsFree('COURSEDOWN', $learner->id))->toBeTrue()
        // The enrolment waits for a retry, which is handed the same one.
        ->and(CourseEnrollment::query()->sole()->status)->toBe('pending');

    // The retry, with the gateway back, uses the code once. (PaymentService is
    // a singleton holding the provider it was built with.)
    abandonedFakeBml();
    app()->forgetInstance(App\Domains\Finance\Services\Payment\PaymentService::class);
    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('learn.courses.enroll', $course->id), ['discount_code' => 'COURSEDOWN'])
        ->assertRedirect('https://bml.test/pay/abandoned');
    abandonedConfirm((int) Payment::query()->latest('id')->value('id'));

    expect(CourseEnrollment::query()->sole()->payment_status)->toBe('confirmed')
        ->and(DiscountRedemption::query()->where('status', 'confirmed')->count())->toBe(1)
        ->and(abandonedCodeIsFree('COURSEDOWN', $learner->id))->toBeFalse();
});

function abandonedPaidCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $learner = User::factory()->create();
    makeStudent(['user_id' => $learner->id, 'first_name' => 'Aminath']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Abandoned Paid Course',
        'subject_id' => CourseSubject::query()->where('slug', 'tajweed')->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->registration_fee_amount = 120;
    $course->requires_admin_approval = false;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return [$learner, $course->fresh()];
}
