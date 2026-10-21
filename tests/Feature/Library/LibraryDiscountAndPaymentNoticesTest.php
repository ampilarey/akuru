<?php

use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
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
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * B11b (LIBRARY_PLAN §41, BACKLOG B11, STATUS §5ua): the last two Library
 * notices. A discount used is said in the reader's purchase notice and the
 * writer's sale notice; a card payment the bank refused, or would not start,
 * is told to the office. Nothing is granted on a failure.
 */
uses(RefreshDatabase::class);

/** A BML whose webhook says what the test tells it to; `false` refuses to start. */
function b11bBml(string $status = 'completed', bool $starts = true): void
{
    app()->instance(PaymentProviderInterface::class, new class($status, $starts) implements PaymentProviderInterface
    {
        public function __construct(private string $status, private bool $starts) {}

        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return $this->starts ? new PaymentInitiationResult(true, 'https://bml.test/pay/b11b') : new PaymentInitiationResult(false, null, 'The bank is unavailable.');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-B11B',
                status: $this->status,
                rawPayload: $request->all(),
                isConfirmed: $this->status === 'completed',
            );
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

/** A paid book by a writer, at MVR 100. */
function b11bBook(): array
{
    $writer = User::factory()->create();
    $profile = WriterProfile::query()->create(['user_id' => $writer->id, 'kind' => 'writer', 'display_name' => 'Ustadha Aminath', 'slug' => 'aminath-'.uniqid(), 'status' => 'active', 'approved_at' => now()]);
    $item = app(SaveLibraryItemAction::class)->execute(['title' => 'Nahw Primer', 'content_type' => 'book', 'access_type' => 'paid', 'body' => '<p>Page.</p>']);
    $item->forceFill(['price' => 100, 'writer_id' => $profile->id])->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return [$writer, $item->refresh()];
}

function b11bWebhook(LibraryPurchase $purchase, string $status): void
{
    test()->postJson(url('/webhooks/bml'), [
        'reference' => Payment::query()->whereKey($purchase->payment_id)->value('merchant_reference'),
        'transactionId' => 'BML-B11B',
        'status' => $status,
    ])->assertOk();
}

function b11bBuy(User $reader, LibraryItem $item, ?string $code = null): LibraryPurchase
{
    test()->withoutLocalizationMiddleware()->actingAs($reader)->post(route('public.library.checkout', $item->slug), array_filter(['discount_code' => $code]));

    return LibraryPurchase::query()->where('user_id', $reader->id)->latest('id')->firstOrFail();
}

it('says a discount used to the reader and the writer, in their own language', function () {
    b11bBml();
    [$writer, $item] = b11bBook();
    app(SaveDiscountCodeAction::class)->execute(['code' => 'SAVE10', 'discount_type' => 'fixed', 'discount_value' => 10]);
    $reader = User::factory()->create();

    $purchase = b11bBuy($reader, $item, 'SAVE10');
    expect((float) $purchase->amount)->toBe(90.0);
    b11bWebhook($purchase, 'completed');

    $ready = UserNotification::query()->where('user_id', $reader->id)->where('title', __('library_notices.purchase_ready_title'))->sole();
    expect($ready->message)->toContain(__('library_notices.discount_saved', ['amount' => '10.00']));
    $sale = UserNotification::query()->where('user_id', $writer->id)->where('title', __('library_notices.sale_title'))->sole();
    expect($sale->message)->toContain(__('library_notices.discount_applied', ['amount' => '10.00']));

    app()->setLocale('dv');
    $shown = app(\App\Domains\Notifications\Actions\ListUserNotificationsAction::class)->execute($reader->id)->firstWhere('id', $ready->id);
    expect($shown['message'])->toContain(__('library_notices.discount_saved', ['amount' => '10.00'], 'dv'));
});

it('says nothing of a discount when none was used', function () {
    b11bBml();
    [$writer, $item] = b11bBook();
    $reader = User::factory()->create();
    b11bWebhook(b11bBuy($reader, $item), 'completed');

    expect(UserNotification::query()->where('user_id', $reader->id)->sole()->message)->not->toContain('MVR')
        ->and(UserNotification::query()->where('user_id', $writer->id)->where('title', __('library_notices.sale_title'))->sole()->message)
        ->not->toContain(__('library_notices.discount_applied', ['amount' => '0.00']));
});

it('tells the office when the bank refuses a Library card payment, and grants nothing', function () {
    b11bBml('failed');
    $office = actingSystemAdmin(['library.manage']);
    [, $item] = b11bBook();
    $reader = User::factory()->create(['name' => 'Mariyam Reader']);

    $purchase = b11bBuy($reader, $item);
    b11bWebhook($purchase, 'failed');

    expect(Payment::query()->whereKey($purchase->payment_id)->value('status'))->toBe('failed')
        ->and(LibraryAccessGrant::query()->where('user_id', $reader->id)->exists())->toBeFalse();
    $notice = UserNotification::query()->where('user_id', $office->id)->where('title', __('library_notices.payment_failed_title'))->sole();
    expect($notice->message)->toContain('Nahw Primer')->toContain('Mariyam Reader')->toContain('100.00');
});

it('tells the office when a Library card payment cannot even start', function () {
    b11bBml(starts: false);
    $office = actingSystemAdmin(['library.manage']);
    [, $item] = b11bBook();
    $reader = User::factory()->create(['name' => 'Hawwa Reader']);

    b11bBuy($reader, $item);

    $notice = UserNotification::query()->where('user_id', $office->id)->where('title', __('library_notices.payment_failed_title'))->sole();
    expect($notice->message)->toBe(__('library_notices.payment_not_started_body', ['title' => 'Nahw Primer', 'name' => 'Hawwa Reader', 'amount' => '100.00']));
});
