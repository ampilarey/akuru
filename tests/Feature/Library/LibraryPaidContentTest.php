<?php

use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeBmlProvider(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/xyz');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: (string) $request->input('transactionId'),
                status: (string) $request->input('status'),
                rawPayload: $request->all(),
                isConfirmed: $request->input('status') === 'completed',
            );
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function seedPaidItem(float $price = 150)
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Premium Tafsir Notes',
        'content_type' => 'book',
        'access_type' => 'paid',
        'body' => '<p>Premium page one.</p><!-- pagebreak --><p>Premium page two.</p>',
    ]);
    $item->price = $price;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('locks paid content, sells through BML, and grants access only on webhook confirmation', function () {
    $item = seedPaidItem();
    $buyer = User::factory()->create();

    // Locked before purchase: reader bounces to the item page.
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.library.read', ['slug' => $item->slug]))
        ->assertRedirect(route('public.library.show', $item->slug));

    // Checkout: pending purchase + redirect to the bank (fake provider).
    fakeBmlProvider();
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $item->slug))
        ->assertRedirect('https://bml.test/pay/xyz');

    $purchase = LibraryPurchase::query()->firstOrFail();
    $payment = Payment::query()->findOrFail($purchase->payment_id);
    expect($purchase->status)->toBe('pending')
        ->and($payment->getRawOriginal('payable_type'))->toBe('library_item')
        ->and((int) $payment->payable_id)->toBe($item->id)
        ->and($payment->merchant_reference)->not->toBeNull();

    // The return page grants NOTHING (§43.5) — still unconfirmed.
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.library.payment-return', $item->slug))
        ->assertOk()
        ->assertSee('Confirming your payment');
    expect(LibraryAccessGrant::query()->count())->toBe(0);

    // The BANK WEBHOOK confirms — the listener grants access.
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-LIB-001',
        'status' => 'completed',
    ])->assertStatus(200);

    expect($payment->refresh()->status)->toBe('confirmed');
    $purchase->refresh();
    expect($purchase->status)->toBe('paid')
        ->and($purchase->purchased_at)->not->toBeNull();
    $grant = LibraryAccessGrant::query()->firstOrFail();
    expect((int) $grant->user_id)->toBe($buyer->id)
        ->and((int) $grant->library_item_id)->toBe($item->id)
        ->and($grant->source_type)->toBe('purchase');

    // A webhook retry does not stack grants or re-flip the purchase.
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-LIB-001',
        'status' => 'completed',
    ])->assertStatus(200);
    expect(LibraryAccessGrant::query()->count())->toBe(1);

    // Reader now opens; the return page shows confirmed.
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.library.read', ['slug' => $item->slug]))
        ->assertOk()
        ->assertSee('Premium page one', false);
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.library.payment-return', $item->slug))
        ->assertOk()
        ->assertSee('Payment confirmed');

    // Other users stay locked out.
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $item->slug]))
        ->assertRedirect(route('public.library.show', $item->slug));
});

it('refuses checkout for free items, owned items, and guests; my-library shows purchases', function () {
    $item = seedPaidItem();
    $buyer = User::factory()->create();

    // Guests cannot check out.
    $this->withoutLocalizationMiddleware()
        ->post(route('public.library.checkout', $item->slug))
        ->assertForbidden();

    // Free items are not for sale.
    $free = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Free Notes',
        'content_type' => 'article',
        'access_type' => 'free_public',
        'body' => '<p>Free.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($free->id, User::factory()->create()->id);
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $free->slug))
        ->assertSessionHasErrors('item');

    // Already-granted buyers are not charged again.
    LibraryAccessGrant::query()->create([
        'user_id' => $buyer->id,
        'library_item_id' => $item->id,
        'source_type' => 'admin',
        'status' => 'active',
    ]);
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $item->slug))
        ->assertSessionHasErrors('item');

    // Purchase history shows on my-library.
    LibraryPurchase::query()->create([
        'user_id' => $buyer->id,
        'library_item_id' => $item->id,
        'amount' => 150,
        'currency' => 'MVR',
        'status' => 'paid',
        'purchased_at' => now(),
    ]);
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('Premium Tafsir Notes')
        ->assertSee('150');
});
