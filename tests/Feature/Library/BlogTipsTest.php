<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Finance\Actions\RefundPaymentAction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL6a (STATUS §5sm, §7.1, rule 12): tips to a blog's writer —
 * paid and earned only on BML's webhook (or at once from the wallet), less
 * Akuru's share, reversed with new rows on a refund; the note checked first.
 */
uses(RefreshDatabase::class);

function bl6Bank(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/tip');
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

function bl6Blog(bool $tips = true): array
{
    $owner = User::factory()->create();
    $profile = WriterProfile::query()->create(['user_id' => $owner->id, 'kind' => 'student', 'display_name' => 'Starling', 'slug' => 'starling-'.$owner->id, 'status' => 'active', 'approved_at' => now(), 'tips_enabled' => $tips]);
    $post = LibraryItem::query()->create(['title' => 'The garden begins', 'slug' => 'garden-'.$owner->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'readers_notified_at' => now(), 'writer_id' => $profile->id, 'body' => '<p>Beans.</p>']);

    return [$owner, $profile, $post];
}

function bl6Webhook(Payment $payment): void
{
    test()->postJson(url('/webhooks/bml'), ['reference' => $payment->merchant_reference, 'transactionId' => 'BML-TIP-'.$payment->id, 'status' => 'completed'])->assertStatus(200);
}

it('pays and earns a card tip only on the webhook, once, less Akuru\'s share', function () {
    bl6Bank();
    [, $profile, $post] = bl6Blog();
    $reader = User::factory()->create();
    $web = test()->withoutLocalizationMiddleware();

    $web->get("/library/authors/{$profile->slug}")->assertSee('data-testid="tip-box"', false);
    $web->post("/library/authors/{$profile->slug}/tip", ['amount' => 10])->assertRedirect(route('login'));
    $web->actingAs($reader)->post("/library/authors/{$profile->slug}/tip", ['amount' => 25, 'item_id' => $post->id, 'note' => 'Thank you <b>for</b> the beans'])
        ->assertRedirect('https://bml.test/pay/tip');

    $tip = LibraryTip::query()->sole();
    $payment = Payment::query()->findOrFail($tip->payment_id);
    expect($tip->status)->toBe('pending')->and((float) $tip->amount)->toBe(25.0)->and($tip->library_item_id)->toBe($post->id)
        ->and($tip->note)->toBe('Thank you for the beans')->and($tip->note_status)->toBe('pending')
        ->and($payment->getRawOriginal('payable_type'))->toBe('library_tip');

    // Coming back from the bank grants nothing.
    $web->actingAs($reader)->get("/library/authors/{$profile->slug}?tip=returned")->assertOk()->assertSee('data-testid="tip-returned"', false);
    expect(WriterEarning::query()->count())->toBe(0)->and($tip->fresh()->status)->toBe('pending');

    bl6Webhook($payment);
    bl6Webhook($payment);
    $earning = WriterEarning::query()->sole();
    expect($tip->fresh()->status)->toBe('paid')->and($earning->kind)->toBe('tip')->and($earning->library_tip_id)->toBe($tip->id)
        ->and((float) $earning->writer_amount)->toBe(22.5)->and((float) $earning->platform_commission)->toBe(2.5)
        ->and((float) $earning->bml_amount)->toBe(25.0)->and($earning->library_purchase_id)->toBeNull();
});

it('takes Akuru\'s share from the setting, and pays from the wallet at once', function () {
    config(['library.blog.tip_share_percent' => 20]);
    [, $profile] = bl6Blog();
    $reader = User::factory()->create();
    app(CreditWalletAction::class)->execute($reader->id, 30, 'gift_card', null, 'Gift card');

    test()->withoutLocalizationMiddleware()->actingAs($reader)->post("/library/authors/{$profile->slug}/tip", ['amount' => 10, 'wallet' => true])->assertSessionHas('success', __('blog.flash_tip_thanks'));
    $earning = WriterEarning::query()->sole();
    expect(LibraryTip::query()->sole()->status)->toBe('paid')->and((float) $earning->writer_amount)->toBe(8.0)->and((float) $earning->wallet_amount)->toBe(10.0);

    // Not enough in the wallet: nothing is paid or earned.
    test()->withoutLocalizationMiddleware()->actingAs($reader)->post("/library/authors/{$profile->slug}/tip", ['amount' => 50, 'wallet' => true])->assertSessionHasErrors();
    expect(LibraryTip::query()->where('status', 'paid')->count())->toBe(1)->and(WriterEarning::query()->count())->toBe(1);
});

it('reverses a refunded tip with a new row and claws back its earning, once', function () {
    bl6Bank();
    [, $profile] = bl6Blog();
    $reader = User::factory()->create();
    test()->withoutLocalizationMiddleware()->actingAs($reader)->post("/library/authors/{$profile->slug}/tip", ['amount' => 10]);
    $tip = LibraryTip::query()->sole();
    bl6Webhook(Payment::query()->findOrFail($tip->payment_id));

    app(RefundPaymentAction::class)->execute($tip->payment_id, 10, 'wallet');
    $reversal = LibraryTip::query()->where('reverses_tip_id', $tip->id)->sole();
    expect($tip->fresh()->status)->toBe('paid')->and((float) $tip->fresh()->amount)->toBe(10.0)
        ->and($reversal->status)->toBe('refunded')->and((float) $reversal->amount)->toBe(-10.0)
        ->and(WriterEarning::query()->sole()->status)->toBe('refunded');
    app(\App\Domains\Library\Actions\Blog\ReverseTipAction::class)->execute($tip->id);
    expect(LibraryTip::query()->count())->toBe(2);
});

it('refuses a tip where tips are off, to oneself, or of an amount not offered', function () {
    bl6Bank();
    [$owner, $profile] = bl6Blog();
    [, $closed] = bl6Blog(tips: false);
    $reader = User::factory()->create();
    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($reader)->post("/library/authors/{$closed->slug}/tip", ['amount' => 10])->assertSessionHasErrors('tip');
    $web->get("/library/authors/{$closed->slug}")->assertDontSee('data-testid="tip-box"', false);
    $web->actingAs($owner)->post("/library/authors/{$profile->slug}/tip", ['amount' => 10])->assertSessionHasErrors('tip');
    $web->actingAs($reader)->post("/library/authors/{$profile->slug}/tip", ['amount' => 7])->assertSessionHasErrors('amount');
    expect(LibraryTip::query()->count())->toBe(0);
});

it('shows the writer a note only once the office publishes it, and lists tips with CSVs', function () {
    bl6Bank();
    [$owner, $profile] = bl6Blog();
    $reader = User::factory()->create(['name' => 'Mariyam Ahmed']);
    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($reader)->post("/library/authors/{$profile->slug}/tip", ['amount' => 5, 'note' => 'Keep writing!']);
    $tip = LibraryTip::query()->sole();
    bl6Webhook(Payment::query()->findOrFail($tip->payment_id));

    $web->actingAs($owner)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('blog.tips.0.note', null)->where('blog.tips.0.from', 'Mariyam')->where('blog.tips.0.writer_share', 4.5));
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get('/admin/library/tips?notes=1')->assertInertia(fn (Assert $p) => $p->component('Library/BlogTips')->where('tips.0.note', 'Keep writing!')->where('tips.0.note_status', 'pending'));
    $web->actingAs($office)->post("/admin/library/tips/{$tip->id}/note", ['decision' => 'publish'])->assertSessionHas('success');
    $web->actingAs($owner)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('blog.tips.0.note', 'Keep writing!'));

    expect($web->actingAs($owner)->get('/my-blog/tips/export')->assertOk()->streamedContent())->toContain('Keep writing!')->toContain('4.50');
    expect($web->actingAs($office)->get('/admin/library/tips/export')->assertOk()->streamedContent())->toContain('Mariyam Ahmed');
    $web->actingAs($owner)->get('/admin/library/tips')->assertForbidden();

    $web->actingAs($owner)->post('/my-blog/tips', ['enabled' => false])->assertSessionHas('success', __('blog.flash_tips_off'));
    expect($profile->fresh()->tips_enabled)->toBeFalse();
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['blog.tip_heading', 'blog.tips_switch', 'nav.blog_tips', 'admin.library_settings_label_tip_share'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
});
