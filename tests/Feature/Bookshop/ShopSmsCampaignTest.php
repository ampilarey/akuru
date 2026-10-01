<?php

use App\Domains\Bookshop\Actions\ShopSmsCampaignAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ShopSmsCampaign;
use App\Domains\Bookshop\Models\ShopSmsCampaignRecipient;
use App\Domains\Bookshop\Models\ShopSmsOptin;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Support\SmsSegments;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P7b: SMS offers. Only a customer who ticked the box
 * at checkout gets one; the office sees the cost before sending and cannot
 * pass the month's budget; each message carries its own stop link, and the
 * link or a STOP reply stops the next one.
 */
beforeEach(function () {
    Mail::fake();
});

function offerSms(): object
{
    $sms = new class implements SmsSenderInterface
    {
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = [$phoneNumber, $message, $options['type'] ?? null];

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return ['success' => true, 'driver' => 'log'];
        }
    };
    app()->instance(SmsSenderInterface::class, $sms);

    return $sms;
}

function offersOnly(object $sms): array
{
    return array_values(array_filter($sms->sent, fn ($s) => $s[2] === 'bookshop_campaign'));
}

function offerWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function offerOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function offerShop(string $slug): array
{
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $method = VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 0, 'handling_days' => 1, 'is_active' => true]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => $slug.'-book', 'title' => 'Book', 'price' => 50, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $method, $book];
}

function offerBuy(array $shop, string $phone, bool $optIn): User
{
    [$vendor, $method, $book] = $shop;
    $customer = User::factory()->create(['phone' => $phone]);
    app(CreditWalletAction::class)->execute($customer->id, 500, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    offerWeb()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Customer', 'phone' => $phone, 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'm'.$method->id], 'payment_method' => 'wallet', 'sms_offers' => $optIn ? '1' : null,
    ])->assertSessionHasNoErrors();

    return $customer;
}

it('opts in only the customer who ticked the box at checkout', function () {
    offerSms();
    $shop = offerShop('fitrah');
    $yes = offerBuy($shop, '+960 771-1111', true);
    offerBuy($shop, '7712222', false);

    expect(ShopSmsOptin::query()->pluck('phone')->all())->toBe(['7711111'])
        ->and(ShopSmsOptin::query()->value('user_id'))->toBe($yes->id);
    // The box is on the checkout page, unticked.
    CartItem::query()->create(['cart_id' => Cart::query()->firstOrCreate(['user_id' => $yes->id])->id, 'product_id' => $shop[2]->id, 'quantity' => 1]);
    offerWeb()->actingAs($yes)->get(route('public.shop.checkout'))->assertOk()->assertSee('data-testid="sms-offers"', false)->assertDontSee('checked data-testid="sms-offers"', false);
});

it('shows the office who would get it and what it costs, and sends with a stop link each', function () {
    $sms = offerSms();
    $fitrah = offerShop('fitrah');
    $noor = offerShop('noor');
    offerBuy($fitrah, '7711111', true);
    offerBuy($noor, '7713333', true);
    offerBuy($noor, '7714444', false);
    $office = offerOffice();

    offerWeb()->actingAs($office)->get(route('admin.bookshop.campaigns'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Campaigns')->where('summary.opted_in', 2)->where('summary.rate', '0.25')
            ->where('summary.shops', fn ($shops) => collect($shops)->firstWhere('name', 'Noor')['opted_in_buyers'] === 1));

    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.send'), ['audience' => 'shop_buyers', 'vendor_id' => $noor[0]->id, 'message' => 'Eid sale: 20% off at Noor'])
        ->assertSessionHasNoErrors();
    $campaign = ShopSmsCampaign::query()->sole();
    expect($campaign->recipients)->toBe(1)->and($campaign->segments)->toBe(1)->and((string) $campaign->cost)->toBe('0.25')
        ->and($campaign->status)->toBe('sent')->and($campaign->sent_count)->toBe(1);
    $offers = offersOnly($sms);
    $token = ShopSmsOptin::query()->where('phone', '7713333')->value('token');
    expect($offers)->toHaveCount(1)->and($offers[0][0])->toBe('7713333')
        ->and($offers[0][1])->toStartWith('Eid sale: 20% off at Noor')->toContain(route('public.shop.sms.stop', $token));

    // Everyone who opted in: both, never the one who did not.
    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.send'), ['audience' => 'opted_in', 'message' => 'New term books are in'])->assertSessionHasNoErrors();
    expect(collect(offersOnly($sms))->pluck(0)->all())->toBe(['7713333', '7711111', '7713333'])
        ->and(collect(offersOnly($sms))->pluck(0)->all())->not->toContain('7714444');

    $csv = offerWeb()->actingAs($office)->get(route('admin.bookshop.campaigns.export'))->streamedContent();
    expect($csv)->toContain('Eid sale: 20% off at Noor')->toContain('New term books are in');
    offerWeb()->actingAs(User::factory()->create())->get(route('admin.bookshop.campaigns'))->assertForbidden();
    offerWeb()->actingAs(User::factory()->create())->post(route('admin.bookshop.campaigns.send'), ['audience' => 'opted_in', 'message' => 'x'])->assertForbidden();
});

it('stops by the link (confirmed with a button) and by a STOP reply', function () {
    $sms = offerSms();
    $shop = offerShop('fitrah');
    offerBuy($shop, '7711111', true);
    offerBuy($shop, '7712222', true);
    $office = offerOffice();
    $token = ShopSmsOptin::query()->where('phone', '7711111')->value('token');

    // Opening the link stops nothing; the button does.
    offerWeb()->get(route('public.shop.sms.stop', $token))->assertOk()->assertSee('data-testid="sms-stop-confirm"', false)->assertDontSee('7711111');
    expect(ShopSmsOptin::query()->where('token', $token)->value('opted_out_at'))->toBeNull();
    offerWeb()->post(route('public.shop.sms.stop.confirm', $token))->assertRedirect(route('public.shop.sms.stop', $token));
    offerWeb()->get(route('public.shop.sms.stop', $token))->assertSee('data-testid="sms-stopped"', false);
    offerWeb()->get(route('public.shop.sms.stop', 'zzzzzzzzzzzz'))->assertNotFound();

    // The gateway's keyword hook: only STOP (or UNSUBSCRIBE) counts.
    offerWeb()->post(route('public.shop.sms.opt-out'), ['phone' => '+9607712222', 'keyword' => 'hello'])->assertNoContent();
    expect(ShopSmsOptin::query()->where('phone', '7712222')->value('opted_out_at'))->toBeNull();
    offerWeb()->post(route('public.shop.sms.opt-out'), ['phone' => '+9607712222', 'keyword' => 'stop please'])->assertNoContent();
    expect(ShopSmsOptin::query()->where('phone', '7712222')->value('opted_out_at'))->not->toBeNull();

    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.send'), ['audience' => 'opted_in', 'message' => 'Anyone?'])->assertSessionHasErrors('audience');
    expect(offersOnly($sms))->toBe([]);

    // Ticking the box again starts them again.
    offerBuy($shop, '7711111', true);
    expect(ShopSmsOptin::query()->where('phone', '7711111')->value('opted_out_at'))->toBeNull();
});

it('skips a phone that stopped between sending and delivery', function () {
    $sms = offerSms();
    $shop = offerShop('fitrah');
    offerBuy($shop, '7711111', true);
    $campaign = ShopSmsCampaign::query()->create(['audience' => 'opted_in', 'message' => 'Hi', 'recipients' => 1, 'segments' => 1, 'rate' => 0.25, 'cost' => 0.25, 'status' => 'queued', 'created_by' => offerOffice()->id]);
    $optin = ShopSmsOptin::query()->sole();
    ShopSmsCampaignRecipient::query()->create(['shop_sms_campaign_id' => $campaign->id, 'shop_sms_optin_id' => $optin->id, 'phone' => $optin->phone, 'body' => 'Hi', 'status' => 'pending']);
    app(ShopSmsCampaignAction::class)->optOut($optin->token);

    app(ShopSmsCampaignAction::class)->deliver($campaign->id);
    expect(offersOnly($sms))->toBe([])->and(ShopSmsCampaignRecipient::query()->value('status'))->toBe('skipped');
});

it('refuses a campaign the month\'s budget cannot pay, and lets the office set the budget and the rate', function () {
    offerSms();
    $shop = offerShop('fitrah');
    offerBuy($shop, '7711111', true);
    offerBuy($shop, '7712222', true);
    $office = offerOffice();

    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.settings'), ['rate' => 1.5, 'budget' => 2])->assertSessionHasNoErrors();
    expect(app(ShopSmsCampaignAction::class)->settings())->toBe(['rate' => '1.50', 'budget' => '2.00']);
    // Two people × one message × 1.50 = 3.00 > 2.00.
    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.send'), ['audience' => 'opted_in', 'message' => 'Sale'])->assertSessionHasErrors('message');
    expect(ShopSmsCampaign::query()->count())->toBe(0);

    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.settings'), ['rate' => 1.5, 'budget' => 3])->assertSessionHasNoErrors();
    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.send'), ['audience' => 'opted_in', 'message' => 'Sale'])->assertSessionHasNoErrors();
    // The month is now spent.
    offerWeb()->actingAs($office)->post(route('admin.bookshop.campaigns.send'), ['audience' => 'opted_in', 'message' => 'Sale again'])->assertSessionHasErrors('message');
    offerWeb()->actingAs($office)->get(route('admin.bookshop.campaigns'))->assertInertia(fn ($page) => $page->where('summary.spent', '3.00')->where('summary.left', '0.00'));
});

it('counts a message as the gateway bills it', function () {
    expect(SmsSegments::count(str_repeat('a', 160)))->toBe(1)
        ->and(SmsSegments::count(str_repeat('a', 161)))->toBe(2)
        ->and(SmsSegments::count(str_repeat('ދ', 70)))->toBe(1)
        ->and(SmsSegments::count(str_repeat('ދ', 71)))->toBe(2)
        ->and(SmsSegments::count(str_repeat('€', 80)))->toBe(1)
        ->and(SmsSegments::count(str_repeat('€', 81)))->toBe(2);
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['sms_offers_optin', 'campaigns_title', 'campaign_send', 'sms_stop_title', 'campaign_stop_suffix', 'error_campaign_budget'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
        expect(__('nav.sms_campaigns', [], $locale))->not->toBe(__('nav.sms_campaigns', [], 'en'));
    }
});
