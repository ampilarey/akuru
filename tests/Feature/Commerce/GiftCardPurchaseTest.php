<?php

use App\Domains\Commerce\Actions\RedeemGiftCardAction;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\GiftCardOrder;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\GiftCardCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §15.3: "select amount → recipient details → message → BML →
 * webhook → generate code", and §15.4: never with a discount, never from a
 * wallet. Until 2026-09-25 the office issued every gift card by hand.
 */
function fakeBml(bool $initiates = true): void
{
    app()->instance(PaymentProviderInterface::class, new class($initiates) implements PaymentProviderInterface
    {
        public function __construct(private bool $initiates) {}

        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return $this->initiates
                ? new PaymentInitiationResult(true, 'https://bml.test/pay/gift')
                : new PaymentInitiationResult(false, null, null, 'Gateway down');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-GIFT',
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

it('shows the gift card page to a visitor, with buying behind sign-in', function () {
    $this->withoutLocalizationMiddleware()->get(route('public.gift-cards.index'))
        ->assertOk()
        ->assertSee('Give the gift of reading')
        ->assertSee('Sign in to buy a gift card.')
        ->assertSee('MVR 100')
        ->assertSee('MVR 1,000');

    $this->withoutLocalizationMiddleware()
        ->post(route('public.gift-cards.purchase'), ['amount' => 100, 'recipient_name' => 'Nobody'])
        ->assertForbidden();
});

it('takes the buyer to BML and issues the card, once, on the webhook — the code goes to the recipient', function () {
    Mail::fake();
    fakeBml();
    $buyer = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.gift-cards.purchase'), [
            'amount' => 250,
            'recipient_name' => 'Hawwa',
            'recipient_email' => 'hawwa@example.test',
            'message' => 'Happy reading!',
            // §15.4: a discount code sent along is not a field and changes nothing.
            'discount_code' => 'HALF',
        ])
        ->assertRedirect('https://bml.test/pay/gift');

    $order = GiftCardOrder::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    expect($order->status)->toBe('pending')
        ->and((string) $order->amount)->toBe('250.00')
        ->and((string) $payment->amount)->toBe('250.00')
        ->and($payment->getRawOriginal('payable_type'))->toBe('gift_card_order')
        ->and((int) $payment->payable_id)->toBe($order->id)
        ->and(GiftCard::query()->count())->toBe(0);

    // Before the bank speaks the return page says so, and no card exists.
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.gift-cards.return'))
        ->assertOk()
        ->assertSee('Confirming your payment');

    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-GIFT',
        'status' => 'completed',
    ])->assertStatus(200);

    $order->refresh();
    $card = GiftCard::query()->firstOrFail();
    expect($order->status)->toBe('paid')
        ->and((int) $order->gift_card_id)->toBe($card->id)
        ->and($order->delivered_via)->toBe('email')
        ->and($order->delivered_to)->toBe('h***@example.test')
        ->and((string) $card->balance_amount)->toBe('250.00')
        ->and($card->recipient_name)->toBe('Hawwa')
        ->and((int) $card->purchaser_user_id)->toBe($buyer->id);

    // The plain code exists in the email and nowhere else.
    $plain = null;
    Mail::assertQueued(GiftCardCodeMail::class, function (GiftCardCodeMail $mail) use (&$plain) {
        $plain = $mail->plainCode;

        return $mail->hasTo('hawwa@example.test') && $mail->recipientName === 'Hawwa' && $mail->message === 'Happy reading!';
    });
    expect($plain)->toMatch('/^AKG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/')
        ->and(GiftCardOrder::query()->whereRaw("CAST(id AS CHAR) = '0' OR 0")->count())->toBe(0);
    foreach (GiftCardOrder::query()->first()->getAttributes() as $value) {
        expect((string) $value)->not->toContain($plain);
    }

    // The recipient redeems it and has wallet money.
    $recipient = User::factory()->create();
    app(RedeemGiftCardAction::class)->execute($recipient->id, $plain);
    expect((string) Wallet::query()->where('user_id', $recipient->id)->value('balance'))->toBe('250.00');

    // The same webhook again issues nothing more.
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-GIFT',
        'status' => 'completed',
    ]);
    expect(GiftCard::query()->count())->toBe(1);
    Mail::assertQueuedCount(1);

    // The buyer's return page and wallet show where it went, not the code.
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.gift-cards.return'))
        ->assertOk()
        ->assertSee('Payment confirmed')
        ->assertSee('h***@example.test')
        ->assertDontSee($plain);
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.wallet'))
        ->assertOk()
        ->assertSee('Gift cards you bought')
        ->assertSee('Hawwa')
        ->assertDontSee($plain);
});

it('sends the code by SMS when only a mobile is given', function () {
    Mail::fake();
    fakeBml();
    $sent = [];
    app()->instance(SmsSenderInterface::class, new class($sent) implements SmsSenderInterface
    {
        public function __construct(public array &$sent) {}

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = [$phoneNumber, $message];

            return ['success' => true];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return ['success' => true];
        }
    });
    $buyer = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.gift-cards.purchase'), [
            'amount' => 100,
            'recipient_name' => 'Ibrahim',
            'recipient_mobile' => '+960 7 777 777',
        ])
        ->assertRedirect('https://bml.test/pay/gift');
    $payment = Payment::query()->firstOrFail();
    $this->postJson(url('/webhooks/bml'), ['reference' => $payment->merchant_reference, 'transactionId' => 'X', 'status' => 'completed']);

    $order = GiftCardOrder::query()->firstOrFail();
    expect($order->delivered_via)->toBe('sms')
        ->and($order->delivered_to)->toBe('********777')
        ->and($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe('+9607777777')
        ->and($sent[0][1])->toContain('AKG-');
    Mail::assertNothingQueued();
});

it('refuses an amount outside the range, a fraction, and a recipient with no way to reach them', function () {
    fakeBml();
    $buyer = User::factory()->create();
    $post = fn (array $data) => test()->withoutLocalizationMiddleware()->actingAs($buyer)
        ->from(route('public.gift-cards.index'))
        ->post(route('public.gift-cards.purchase'), $data + ['recipient_name' => 'Aisha', 'recipient_email' => 'a@example.test']);

    $post(['amount' => 10])->assertSessionHasErrors('amount');
    $post(['amount' => 99999])->assertSessionHasErrors('amount');
    $post(['amount' => 100.5])->assertSessionHasErrors('amount');
    test()->withoutLocalizationMiddleware()->actingAs($buyer)
        ->from(route('public.gift-cards.index'))
        ->post(route('public.gift-cards.purchase'), ['amount' => 100, 'recipient_name' => 'Aisha'])
        ->assertSessionHasErrors('recipient_email');

    expect(GiftCardOrder::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('marks the order failed and says so when the gateway will not start', function () {
    fakeBml(initiates: false);
    $buyer = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.gift-cards.purchase'), ['amount' => 100, 'recipient_name' => 'Aisha', 'recipient_email' => 'a@example.test'])
        ->assertRedirect(route('public.gift-cards.index'))
        ->assertSessionHas('error');

    expect(GiftCardOrder::query()->value('status'))->toBe('failed')
        ->and(GiftCard::query()->count())->toBe(0);
});

it('gives a signed-in person the Library, My library and My wallet in the shell and on the public site', function () {
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('nav.groups', function ($groups) {
            $mine = collect($groups)->firstWhere('key', 'mine');
            $hrefs = collect($mine['items'] ?? [])->pluck('href')->map(fn ($href) => preg_replace('#^/[a-z]{2}/#', '/', $href))->all();

            return in_array('/library', $hrefs, true) && in_array('/my-library', $hrefs, true) && in_array('/my-wallet', $hrefs, true);
        }));

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.index'))
        ->assertOk()
        ->assertSee('data-testid="nav-my-library"', false)
        ->assertSee('data-testid="nav-my-wallet"', false);
});
