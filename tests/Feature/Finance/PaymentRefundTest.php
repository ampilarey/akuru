<?php

use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\RefundPaymentAction;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Finance\Models\PaymentRefund;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\GrantLibraryAccessAction;
use App\Domains\Library\Actions\ResolveLibraryAccessAction;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\People\Models\RegistrationStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function seedConfirmedCoursePayment(float $amount = 100): array
{
    $payer = User::factory()->create();
    $student = RegistrationStudent::create([
        'user_id' => $payer->id,
        'first_name' => 'Mariyam',
        'last_name' => 'Shifa',
        'dob' => now()->subYears(26),
    ]);
    $course = Course::factory()->create([
        'registration_fee_amount' => $amount,
        'requires_admin_approval' => false,
    ]);
    $enrollment = CourseEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'payment_status' => 'confirmed',
        'enrolled_at' => now(),
    ]);
    $payment = Payment::create([
        'user_id' => $payer->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => $amount,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-REFUND-'.$course->id,
        'paid_at' => now(),
        'confirmed_at' => now(),
    ]);
    PaymentItem::create([
        'payment_id' => $payment->id,
        'enrollment_id' => $enrollment->id,
        'course_id' => $course->id,
        'amount' => $amount,
    ]);
    $enrollment->update(['payment_id' => $payment->id]);

    return ['payer' => $payer, 'enrollment' => $enrollment, 'payment' => $payment];
}

it('refunds a course payment to the wallet, cancels the enrollment, frees the discount slot', function () {
    $ctx = seedConfirmedCoursePayment(100);
    $code = app(SaveDiscountCodeAction::class)->execute([
        'code' => 'REF10',
        'discount_type' => 'fixed',
        'discount_value' => 10,
    ]);
    DiscountRedemption::query()->create([
        'discount_code_id' => $code->id,
        'user_id' => $ctx['payer']->id,
        'purchase_type' => 'course_enrollment',
        'purchase_id' => $ctx['enrollment']->id,
        'amount_discounted' => 10,
        'status' => 'confirmed',
    ]);
    $admin = actingPeopleAdmin(['payments.refund']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.payments.refund', $ctx['payment']->id), [
            'amount' => 100,
            'destination' => 'wallet',
            'reason' => 'Course cancelled by institute',
        ])->assertSessionHasNoErrors()->assertRedirect();

    $refund = PaymentRefund::query()->firstOrFail();
    expect((string) $refund->amount)->toBe('100.00')
        ->and($refund->destination)->toBe('wallet')
        ->and((int) $refund->refunded_by_user_id)->toBe($admin->id)
        ->and($ctx['payment']->fresh()->status)->toBe('refunded');

    // Money went back through the append-only wallet ledger.
    expect((string) Wallet::query()->where('user_id', $ctx['payer']->id)->value('balance'))->toBe('100.00');
    $ledger = WalletTransaction::query()->firstOrFail();
    expect($ledger->source_type)->toBe('refund')
        ->and((int) $ledger->source_id)->toBe($refund->id);

    // Access taken back; discount slot released.
    $enrollment = $ctx['enrollment']->fresh();
    expect($enrollment->payment_status)->toBe('refunded')
        ->and($enrollment->status)->toBe('cancelled');
    expect(DiscountRedemption::query()->firstOrFail()->status)->toBe('released');
});

it('keeps access on a partial refund and blocks over-refunding', function () {
    $ctx = seedConfirmedCoursePayment(200);

    app(RefundPaymentAction::class)->execute($ctx['payment']->id, 50, 'manual', null, 'Goodwill');
    $enrollment = $ctx['enrollment']->fresh();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->payment_status)->toBe('confirmed')
        ->and($ctx['payment']->fresh()->status)->toBe('confirmed')
        ->and(Wallet::query()->count())->toBe(0); // manual moves no wallet money

    // Over the remainder → refused, nothing recorded.
    expect(fn () => app(RefundPaymentAction::class)->execute($ctx['payment']->id, 151, 'manual'))
        ->toThrow(ValidationException::class);
    expect(PaymentRefund::query()->count())->toBe(1);

    // Refunding the exact remainder completes the refund and cancels.
    app(RefundPaymentAction::class)->execute($ctx['payment']->id, 150, 'manual');
    expect($ctx['payment']->fresh()->status)->toBe('refunded')
        ->and($ctx['enrollment']->fresh()->status)->toBe('cancelled');

    // A refunded payment refuses further refunds.
    expect(fn () => app(RefundPaymentAction::class)->execute($ctx['payment']->id, 1, 'manual'))
        ->toThrow(ValidationException::class);
});

it('revokes the library grant when a library purchase is fully refunded', function () {
    $reader = User::factory()->create();
    $item = LibraryItem::query()->create([
        'title' => 'Refundable Tafsir',
        'slug' => 'refundable-tafsir',
        'content_type' => 'book',
        'access_type' => 'paid',
        'status' => 'published',
        'price' => 60,
        'currency' => 'MVR',
    ]);
    $payment = Payment::create([
        'user_id' => $reader->id,
        'amount' => 60,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-LIB-REFUND-1',
        'payable_type' => 'library_item',
        'payable_id' => $item->id,
        'paid_at' => now(),
        'confirmed_at' => now(),
    ]);
    $purchase = LibraryPurchase::query()->create([
        'user_id' => $reader->id,
        'library_item_id' => $item->id,
        'payment_id' => $payment->id,
        'amount' => 60,
        'currency' => 'MVR',
        'status' => 'paid',
        'purchased_at' => now(),
    ]);
    app(GrantLibraryAccessAction::class)->execute($reader->id, $item->id, 'purchase', $purchase->id);
    expect(app(ResolveLibraryAccessAction::class)->execute($item, $reader->id)['can_read'])->toBeTrue();

    app(RefundPaymentAction::class)->execute($payment->id, 60, 'wallet');

    expect($purchase->fresh()->status)->toBe('refunded')
        ->and(LibraryAccessGrant::query()->firstOrFail()->status)->toBe('revoked')
        ->and(app(ResolveLibraryAccessAction::class)->execute($item->fresh(), $reader->id)['can_read'])->toBeFalse()
        ->and((string) Wallet::query()->where('user_id', $reader->id)->value('balance'))->toBe('60.00');
});
