<?php

use App\Domains\Bookshop\Actions\Money\LoyaltyRewardsAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\LoyaltyReward;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5lm, Bookstore rewards: off until the office turns them on; then
 * a share of the goods paid for (never delivery, less discount and refunds)
 * goes into the customer's wallet once the order's return window passes —
 * once per order, only for orders paid after it was turned on, capped.
 */
function rewardsOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function rewardOrder(User $buyer, array $overrides = []): Order
{
    $vendor = Vendor::query()->firstOrCreate(['slug' => 'reward-shop'], ['name' => 'Reward Shop', 'code' => 'RWD', 'status' => 'active']);
    $n = strtoupper(Str::random(6));
    $checkout = BookshopCheckout::query()->create(['number' => 'AK-'.$n, 'user_id' => $buyer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['recipient_name' => 'A'], 'subtotal' => 0, 'discount' => 0, 'delivery_total' => 0, 'total' => 0, 'currency' => 'MVR', 'paid_at' => now()]);

    return Order::query()->create($overrides + [
        'number' => 'AK-'.$n.'-RWD', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $buyer->id,
        'status' => 'delivered', 'delivery_kind' => 'courier_male', 'delivery_name' => 'Courier', 'delivery_fee' => 30,
        'address_snapshot' => ['recipient_name' => 'A'], 'subtotal' => 400, 'discount' => 0, 'total' => 430, 'currency' => 'MVR',
        'paid_at' => now(), 'delivered_at' => now(),
    ]);
}

function rewardsAs(?User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('is off until the office turns it on, and pays nothing for the past', function () {
    $buyer = User::factory()->create();
    $old = rewardOrder($buyer, ['paid_at' => now()->subDays(20), 'delivered_at' => now()->subDays(15)]);
    $office = rewardsOffice();

    expect(app(LoyaltyRewardsAction::class)->settings()['on'])->toBeFalse()
        ->and(app(LoyaltyRewardsAction::class)->awardDue())->toBe(0);
    rewardsAs($office)->get(route('admin.bookshop.index'))
        ->assertInertia(fn ($page) => $page->where('rewards.settings.on', false)->where('rewards.paid_count', 0));
    rewardsAs($buyer)->get(route('public.shop.orders.show', $old->number))->assertOk()->assertDontSee('data-testid="order-reward"', false);

    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 2, 'min_order' => 50, 'max_per_order' => 10])
        ->assertSessionHas('success', __('shop.rewards_on_flash'));
    $this->artisan('bookshop:award-rewards')->expectsOutput('Rewards paid: 0')->assertSuccessful();
    expect(LoyaltyReward::query()->count())->toBe(0);
});

it('pays a share of the goods into the wallet once the return window passes, once, and never on delivery', function () {
    $office = rewardsOffice();
    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 2, 'min_order' => 50, 'max_per_order' => 10]);
    $this->travel(1)->minutes();
    $buyer = User::factory()->create();

    // Goods 400 less a 50 discount and a 100 refund = 250; 2% = 5.00. Delivery (30) never counts.
    $due = rewardOrder($buyer, ['discount' => 50, 'total' => 380]);
    OrderRefund::query()->create(['order_id' => $due->id, 'amount' => 100, 'currency' => 'MVR', 'paid_with' => 'wallet', 'status' => 'done']);
    $big = rewardOrder($buyer, ['subtotal' => 2000, 'total' => 2030]);
    $small = rewardOrder($buyer, ['subtotal' => 40, 'total' => 70]);
    $onTheWay = rewardOrder($buyer, ['status' => 'dispatched', 'delivered_at' => null]);

    rewardsAs($buyer)->get(route('public.shop.orders.show', $due->number))
        ->assertSee('data-reward="coming"', false)->assertSee(__('shop.reward_coming', ['amount' => 'MVR 5.00']));

    // Inside the seven-day window: nothing yet.
    $this->artisan('bookshop:award-rewards')->expectsOutput('Rewards paid: 0');
    $this->travel(8)->days();
    $this->artisan('bookshop:award-rewards')->expectsOutput('Rewards paid: 2');
    $this->artisan('bookshop:award-rewards')->expectsOutput('Rewards paid: 0');

    expect(LoyaltyReward::query()->where('order_id', $due->id)->sole())
        ->base_amount->toBe('250.00')->amount->toBe('5.00')->percent->toBe('2.00')
        ->and((string) LoyaltyReward::query()->where('order_id', $big->id)->value('amount'))->toBe('10.00')
        ->and(LoyaltyReward::query()->whereIn('order_id', [$small->id, $onTheWay->id])->exists())->toBeFalse()
        ->and((string) Wallet::query()->where('user_id', $buyer->id)->value('balance'))->toBe('15.00');

    $ledger = WalletTransaction::query()->where('user_id', $buyer->id)->where('source_type', 'loyalty_reward')->get();
    expect($ledger)->toHaveCount(2)
        ->and($ledger->pluck('description')->all())->toContain('Reward: Akuru Bookstore '.$due->number)
        ->and(LoyaltyReward::query()->where('order_id', $due->id)->value('wallet_transaction_id'))->toBe($ledger->firstWhere('source_id', LoyaltyReward::query()->where('order_id', $due->id)->value('id'))->id);

    rewardsAs($buyer)->get(route('public.shop.orders.show', $due->number))
        ->assertSee('data-reward="paid"', false)->assertSee(__('shop.reward_paid', ['amount' => 'MVR 5.00']));
    rewardsAs($office)->get(route('admin.bookshop.index'))
        ->assertInertia(fn ($page) => $page->where('rewards.paid_count', 2)->where('rewards.paid_total', '15.00')->has('rewards.latest', 2));
    $csv = rewardsAs($office)->get(route('admin.bookshop.rewards.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('date,order,customer_id,goods_paid,percent,reward,currency')->toContain($due->number.','.$buyer->id.',250.00,2.00,5.00,MVR');
});

it('starts the clock again when turned off and on, and keeps the office\'s numbers in bounds', function () {
    $office = rewardsOffice();
    $rewards = app(LoyaltyRewardsAction::class);

    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 1, 'min_order' => 0, 'max_per_order' => 50]);
    $first = $rewards->settings()['since'];
    $this->travel(1)->hours();
    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 3, 'min_order' => 0, 'max_per_order' => 50]);
    expect($rewards->settings())->since->toBe($first)->percent->toBe(3.0);

    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 0, 'percent' => 3, 'min_order' => 0, 'max_per_order' => 50])
        ->assertSessionHas('success', __('shop.rewards_off_flash'));
    expect($rewards->settings())->on->toBeFalse()->since->toBeNull();
    $this->travel(1)->hours();
    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 3, 'min_order' => 0, 'max_per_order' => 50]);
    expect($rewards->settings()['since'])->not->toBe($first);

    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 25, 'min_order' => 0, 'max_per_order' => 50])->assertSessionHasErrors('percent');
    rewardsAs($office)->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 1, 'min_order' => -1, 'max_per_order' => 0])->assertSessionHasErrors(['min_order', 'max_per_order']);
    rewardsAs(User::factory()->create())->post(route('admin.bookshop.rewards'), ['on' => 1, 'percent' => 1, 'min_order' => 0, 'max_per_order' => 50])->assertForbidden();
    rewardsAs(User::factory()->create())->get(route('admin.bookshop.rewards.export'))->assertForbidden();
});
