<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Finance\Actions\RefundPaymentAction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryBundleAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\StartLibraryBundleCheckoutAction;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryBundle;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterEarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LIBRARY_PLAN §19, BACKLOG B4 (B4d, STATUS §5un): bundles — paid works sold
 * together at one price. One purchase per work, its share by list price,
 * each writer earning on theirs; by card the grants wait for the webhook
 * (rule 12), by wallet they land at once; a full refund takes them back.
 */
uses(RefreshDatabase::class);

/** A writer's paid, published work at a price. */
function b4dWork(string $title, float $price): LibraryItem
{
    static $writer = null;
    if ($writer === null || ! User::query()->whereKey($writer->id)->exists()) {
        $writer = User::factory()->create();
        $application = app(ApplyAsWriterAction::class)->execute($writer->id, [
            'display_name' => 'Ustadha Aminath', 'bio' => 'Teaches grammar.', 'qualifications' => 'MA', 'expertise' => 'Nahw', 'agreement_accepted' => true,
        ]);
        app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);
    }
    $item = app(SaveWriterItemAction::class)->execute($writer->id, [
        'title' => $title, 'content_type' => 'book', 'access_type' => 'paid', 'body' => '<p>'.$title.' page.</p>', 'abstract' => $title.'.',
    ]);
    $item->forceFill(['price' => $price])->save();

    return app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id)->fresh();
}

/** Arabic Beginner Bundle: 100 + 50 + 50 = 200 apart, 150 together. */
function b4dBundle(): array
{
    $works = [b4dWork('Nahw One', 100), b4dWork('Sarf One', 50), b4dWork('Imla One', 50)];
    $bundle = app(SaveLibraryBundleAction::class)->execute([
        'title' => 'Arabic Beginner Bundle', 'price' => 150, 'item_ids' => array_map(fn ($w) => $w->id, $works), 'is_active' => true,
    ], null, User::factory()->create()->id);

    return [$bundle, $works];
}

function b4dBml(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/bundle');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: (string) $request->input('reference'), providerReference: 'BML-B4D', status: 'completed', rawPayload: $request->all(), isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

it('makes a bundle of two or more paid works only below what they cost apart', function () {
    [$bundle, $works] = b4dBundle();
    expect($bundle->slug)->toBe('arabic-beginner-bundle')->and($bundle->items()->pluck('library_items.id')->all())->toBe(array_map(fn ($w) => $w->id, $works));
    $save = app(SaveLibraryBundleAction::class);
    $by = User::factory()->create()->id;

    expect(fn () => $save->execute(['title' => 'One', 'price' => 10, 'item_ids' => [$works[0]->id]], null, $by))->toThrow(ValidationException::class);
    expect(fn () => $save->execute(['title' => 'Dear', 'price' => 200, 'item_ids' => [$works[1]->id, $works[2]->id]], null, $by))->toThrow(ValidationException::class);
    $free = b4dWork('Free', 10);
    $free->forceFill(['access_type' => 'free_public'])->save();
    expect(fn () => $save->execute(['title' => 'With free', 'price' => 5, 'item_ids' => [$works[1]->id, $free->id]], null, $by))->toThrow(ValidationException::class);
});

it('splits the price by list price, the last share taking the rounding', function () {
    $items = collect([new LibraryItem(['price' => 100]), new LibraryItem(['price' => 50]), new LibraryItem(['price' => 50])]);
    $items->each(fn ($i, $k) => $i->id = $k + 1);
    expect(StartLibraryBundleCheckoutAction::shares($items, 150))->toBe([1 => 75.0, 2 => 37.5, 3 => 37.5]);
    $odd = collect([new LibraryItem(['price' => 10]), new LibraryItem(['price' => 10]), new LibraryItem(['price' => 10])]);
    $odd->each(fn ($i, $k) => $i->id = $k + 1);
    expect(array_sum(StartLibraryBundleCheckoutAction::shares($odd, 20)))->toBe(20.0);
});

it('sells a bundle by wallet: one debit, every work granted, each writer earning on its share', function () {
    [$bundle, $works] = b4dBundle();
    $reader = User::factory()->create();
    app(CreditWalletAction::class)->execute($reader->id, 200, 'admin', null, 'Top-up');
    $web = test()->withoutLocalizationMiddleware();

    $web->get(route('public.library.bundle', $bundle->slug))->assertOk()->assertSee('data-testid="bundle-sign-in"', false)->assertSee('150.00');
    $web->actingAs($reader)->post(route('public.library.bundle.checkout', $bundle->slug), ['pay_with_wallet' => 1])
        ->assertRedirect(route('public.library.bundle', $bundle->slug));

    $purchases = LibraryPurchase::query()->where('library_bundle_id', $bundle->id)->orderBy('id')->get();
    expect($purchases->pluck('status')->unique()->all())->toBe(['paid'])
        ->and($purchases->pluck('amount')->map(fn ($a) => (string) $a)->all())->toBe(['75.00', '37.50', '37.50'])
        ->and((string) Wallet::query()->where('user_id', $reader->id)->value('balance'))->toBe('50.00')
        ->and(LibraryAccessGrant::query()->where('user_id', $reader->id)->where('source_type', 'wallet')->count())->toBe(3)
        ->and(WriterEarning::query()->whereIn('library_purchase_id', $purchases->pluck('id'))->count())->toBe(3);

    // Every work is theirs now: the page says so, and a second buy is refused.
    $web->actingAs($reader)->get(route('public.library.bundle', $bundle->slug))->assertSee('data-testid="bundle-owned"', false);
    $web->actingAs($reader)->post(route('public.library.bundle.checkout', $bundle->slug), ['pay_with_wallet' => 1])->assertSessionHasErrors('bundle');
    expect(LibraryPurchase::query()->count())->toBe(3);
});

it('grants a card-paid bundle only on the webhook, and a full refund takes it all back (rule 12)', function () {
    [$bundle] = b4dBundle();
    b4dBml();
    $reader = User::factory()->create();
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($reader)->post(route('public.library.bundle.checkout', $bundle->slug))->assertRedirect('https://bml.test/pay/bundle');
    $payment = Payment::query()->sole();
    expect($payment->getRawOriginal('payable_type'))->toBe('library_bundle')->and((string) $payment->amount)->toBe('150.00')
        ->and(LibraryPurchase::query()->where('payment_id', $payment->id)->where('status', 'pending')->count())->toBe(3)
        ->and(LibraryAccessGrant::query()->count())->toBe(0);

    // The return address alone grants nothing.
    $web->actingAs($reader)->get(route('public.library.bundle', ['slug' => $bundle->slug, 'paid' => 1]))->assertSee('data-testid="bundle-confirming"', false);
    expect(LibraryAccessGrant::query()->count())->toBe(0);

    $web->postJson(url('/webhooks/bml'), ['reference' => $payment->merchant_reference, 'transactionId' => 'BML-B4D', 'status' => 'completed'])->assertOk();
    expect(LibraryPurchase::query()->where('payment_id', $payment->id)->where('status', 'paid')->count())->toBe(3)
        ->and(LibraryAccessGrant::query()->where('user_id', $reader->id)->where('status', 'active')->count())->toBe(3)
        ->and(WriterEarning::query()->count())->toBe(3);

    app(RefundPaymentAction::class)->execute($payment->id, 150, 'manual', User::factory()->create()->id, 'Asked within the window');
    expect(LibraryPurchase::query()->where('status', 'refunded')->count())->toBe(3)
        ->and(LibraryAccessGrant::query()->where('status', 'revoked')->count())->toBe(3)
        ->and(WriterEarning::query()->where('status', 'refunded')->count())->toBe(3);
});

it('lists bundles on sale for readers and every bundle for the office, which alone may make one', function () {
    [$bundle, $works] = b4dBundle();
    LibraryBundle::query()->create(['slug' => 'off', 'title' => 'Switched off', 'price' => 10, 'is_active' => false])->items()->sync([$works[1]->id, $works[2]->id]);
    $web = test()->withoutLocalizationMiddleware();

    $web->get(route('public.library.promotions'))->assertOk()->assertSee('data-bundle="arabic-beginner-bundle"', false)->assertDontSee('data-bundle="off"', false);
    $web->get(route('public.library.bundle', 'off'))->assertNotFound();

    $web->actingAs(User::factory()->create())->get(route('admin.library.bundles'))->assertForbidden();
    $web->actingAs(User::factory()->create())->post(route('admin.library.bundles.store'), ['title' => 'X', 'price' => 1, 'item_ids' => [$works[1]->id, $works[2]->id]])->assertForbidden();
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get(route('admin.library.bundles'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/Bundles')->has('bundles', 2)->where('bundles.1.slug', 'arabic-beginner-bundle')->where('bundles.1.list_total', '200.00')->has('t.library_bundles_title'));
    $web->actingAs($office)->post(route('admin.library.bundles.store', $bundle->id), ['title' => 'Arabic Beginner Bundle', 'price' => 140, 'item_ids' => [$works[0]->id, $works[1]->id], 'is_active' => true])->assertSessionHasNoErrors();
    expect((string) $bundle->fresh()->price)->toBe('140.00')->and($bundle->items()->count())->toBe(2);
    $csv = $web->actingAs($office)->get(route('admin.library.bundles.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Arabic Beginner Bundle')->toContain('Nahw One; Sarf One');
});
