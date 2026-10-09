<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A wallet payment refused for too small a balance leaves nothing behind
 * (slice W1, STATUS §5pl).
 *
 * The Library's checkout wrote the purchase and the code's redemption, then
 * asked the wallet for the money. When the wallet refused, nothing rolled the
 * first two back: the purchase stayed `pending` for good — My Library listed
 * it — and the redemption counted against the code's limits, so a code good
 * once per reader was spent on a payment that never happened. The course
 * checkout had the same order: a pending enrolment, holding its seat, and the
 * redemption. Both now write those rows, and the debit, in one transaction,
 * as the Bookstore's checkout already did.
 */
uses(RefreshDatabase::class);

function w1PaidItem(float $price = 50): LibraryItem
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'W1 Book '.uniqueFixtureSuffix(),
        'content_type' => 'book',
        'access_type' => 'paid',
        'body' => '<p>W1 page.</p>',
    ]);
    $item->price = $price;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

function w1OncePerReaderCode(string $code = 'W1ONCE'): void
{
    app(SaveDiscountCodeAction::class)->execute([
        'code' => $code,
        'discount_type' => 'fixed',
        'discount_value' => 5,
        'per_user_limit' => 1,
        'can_use_with_wallet' => true,
    ]);
}

function w1PaidCourse(float $fee = 100): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $learner = User::factory()->create();
    makeStudent(['user_id' => $learner->id, 'first_name' => 'Mariyam']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'W1 Paid Course',
        'subject_id' => CourseSubject::query()->where('slug', 'tajweed')->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->registration_fee_amount = $fee;
    $course->requires_admin_approval = false;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return [$learner, $course->fresh()];
}

function w1Balance(int $userId): string
{
    return (string) (Wallet::query()->where('user_id', $userId)->value('balance') ?? '0.00');
}

it('writes no Library purchase and no redemption when the wallet is too low, and the code is still good', function () {
    $item = w1PaidItem(50);
    w1OncePerReaderCode();
    $reader = User::factory()->create();
    app(CreditWalletAction::class)->execute($reader->id, 10, 'admin', null, 'W1 opening credit');

    $this->actingAs($reader)->from('/library/'.$item->slug)
        ->post(route('public.library.checkout', $item->slug), ['pay_with_wallet' => 1, 'discount_code' => 'W1ONCE'])
        ->assertRedirect()
        ->assertSessionHasErrors('amount');

    expect(LibraryPurchase::query()->count())->toBe(0)
        ->and(DiscountRedemption::query()->count())->toBe(0)
        ->and(LibraryAccessGrant::query()->count())->toBe(0)
        ->and(w1Balance($reader->id))->toBe('10.00');

    // Topped up, the same reader buys it with the same once-per-reader code.
    app(CreditWalletAction::class)->execute($reader->id, 100, 'admin', null, 'W1 top-up');
    $this->actingAs($reader)
        ->post(route('public.library.checkout', $item->slug), ['pay_with_wallet' => 1, 'discount_code' => 'W1ONCE'])
        ->assertRedirect(route('public.library.read', ['slug' => $item->slug]));

    expect(LibraryPurchase::query()->sole()->status)->toBe('paid')
        ->and(DiscountRedemption::query()->sole()->status)->toBe('confirmed')
        ->and(w1Balance($reader->id))->toBe('65.00');
});

it('lists no purchase in My Library after a refused wallet payment', function () {
    $item = w1PaidItem(999);
    $reader = User::factory()->create();

    $this->actingAs($reader)
        ->post(route('public.library.checkout', $item->slug), ['pay_with_wallet' => 1])
        ->assertSessionHasErrors('amount');

    $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('No purchases yet.')
        ->assertDontSee($item->title);
});

it('still completes a Library purchase the wallet can pay, and tells the writer and the office after', function () {
    $item = w1PaidItem(40);
    $reader = User::factory()->create();
    app(CreditWalletAction::class)->execute($reader->id, 40, 'admin', null, 'W1 exact credit');

    $this->actingAs($reader)
        ->post(route('public.library.checkout', $item->slug), ['pay_with_wallet' => 1])
        ->assertRedirect(route('public.library.read', ['slug' => $item->slug]));

    expect(LibraryPurchase::query()->sole()->status)->toBe('paid')
        ->and(LibraryAccessGrant::query()->sole()->source_type)->toBe('wallet')
        ->and(w1Balance($reader->id))->toBe('0.00');
});

it('writes no course enrolment, seat or redemption when the wallet is too low, and the code is still good', function () {
    [$learner, $course] = w1PaidCourse(100);
    w1OncePerReaderCode('W1COURSE');
    app(CreditWalletAction::class)->execute($learner->id, 20, 'admin', null, 'W1 opening credit');

    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('learn.courses.enroll', $course->id), ['pay_with_wallet' => 1, 'discount_code' => 'W1COURSE'])
        ->assertSessionHasErrors('amount');

    expect(CourseEnrollment::query()->count())->toBe(0)
        ->and(DiscountRedemption::query()->count())->toBe(0)
        ->and(w1Balance($learner->id))->toBe('20.00');

    app(CreditWalletAction::class)->execute($learner->id, 100, 'admin', null, 'W1 top-up');
    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('learn.courses.enroll', $course->id), ['pay_with_wallet' => 1, 'discount_code' => 'W1COURSE'])
        ->assertRedirect(route('learn.courses.show', $course->id));

    $enrollment = CourseEnrollment::query()->sole();
    expect($enrollment->payment_status)->toBe('confirmed')
        ->and($enrollment->status)->toBe('active')
        ->and(DiscountRedemption::query()->sole()->status)->toBe('confirmed')
        ->and(w1Balance($learner->id))->toBe('25.00');
});

it('never charges again for a course already held, wallet or not', function () {
    [$learner, $course] = w1PaidCourse(100);
    app(CreditWalletAction::class)->execute($learner->id, 300, 'admin', null, 'W1 credit');

    foreach ([1, 2] as $attempt) {
        $this->withoutLocalizationMiddleware()->actingAs($learner)
            ->post(route('learn.courses.enroll', $course->id), ['pay_with_wallet' => 1])
            ->assertRedirect(route('learn.courses.show', $course->id));
    }

    expect(CourseEnrollment::query()->count())->toBe(1)
        ->and(w1Balance($learner->id))->toBe('200.00');
});
