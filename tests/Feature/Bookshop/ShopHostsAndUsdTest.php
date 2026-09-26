<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Support\HostDns;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B9f: other addresses for the shop (§2 "path now, a
 * host per vendor later") — a whole-shop subdomain and a shop's own domain,
 * asked for by the owner and turned on by the office, both sent to the one
 * canonical site — and prices in US dollars as a guide (§11), always
 * charged in MVR.
 */
function hostShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function hostOffice(): User
{
    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function hostAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

/** The path of a named route — the URL generator follows the host of the last request, and these tests visit other hosts. */
function hostPath(string $name, mixed $parameters = []): string
{
    return (string) parse_url(route($name, $parameters), PHP_URL_PATH);
}

beforeEach(function () {
    config(['app.url' => 'https://akuru.test', 'bookshop.hosts.shop_host' => 'shop.akuru.test']);
    // Relative requests go to the canonical site, whatever host the last request used.
    \Illuminate\Support\Facades\URL::forceRootUrl('https://akuru.test');
});

it('lets the owner ask for a domain, and the office check it and turn it on', function () {
    [$fitrah, $owner] = hostShop();
    [$noor, $noorOwner] = hostShop('noor');
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $fitrah->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    $office = hostOffice();

    hostAs($staff)->post(hostPath('vendor.host.save'), ['custom_host' => 'fitrahbooks.mv'])->assertForbidden();
    foreach (['not a domain', 'fitrah.akuru.edu.mv', 'shop.akuru.test', 'akuru.test'] as $bad) {
        hostAs($owner)->post(hostPath('vendor.host.save'), ['custom_host' => $bad])->assertSessionHasErrors('custom_host');
    }
    hostAs($owner)->post(hostPath('vendor.host.save'), ['custom_host' => 'https://WWW.FitrahBooks.mv/shop'])->assertSessionHasNoErrors();
    expect($fitrah->refresh()->custom_host)->toBe('www.fitrahbooks.mv')->and($fitrah->custom_host_status)->toBe('requested');
    expect(UserNotification::query()->where('user_id', $office->id)->where('title', __('shop.notice_host_requested_title'))->exists())->toBeTrue();
    hostAs($noorOwner)->post(hostPath('vendor.host.save'), ['custom_host' => 'www.fitrahbooks.mv'])->assertSessionHasErrors('custom_host');
    hostAs($owner)->get(hostPath('vendor.index'))->assertInertia(fn ($page) => $page->where('shop_settings.custom_host', 'www.fitrahbooks.mv')->where('shop_settings.custom_host_status', 'requested')->where('shop_settings.canonical_host', 'akuru.test'));

    // Requested is not answered: the request carries on as any other (here, to a page that is not there).
    $this->get('http://www.fitrahbooks.mv/zz-none')->assertNotFound();

    // The office checks where it points (the network stood in for), then turns it on.
    app()->instance(HostDns::class, new class extends HostDns
    {
        public function lookup(string $host): array
        {
            return $host === 'akuru.test' ? ['addresses' => ['203.0.113.7'], 'aliases' => []] : ['addresses' => ['203.0.113.7'], 'aliases' => ['akuru.test']];
        }
    });
    hostAs($office)->post(hostPath('admin.bookshop.hosts.check', $fitrah->id))->assertSessionHas('host_check', fn ($c) => $c['points_here'] === true && $c['host'] === 'www.fitrahbooks.mv');
    hostAs($owner)->post(hostPath('admin.bookshop.hosts.decide', $fitrah->id), ['decision' => 'approve'])->assertForbidden();
    hostAs($office)->post(hostPath('admin.bookshop.hosts.decide', $fitrah->id), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($fitrah->refresh()->custom_host_status)->toBe('active');
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_host_active_title'))->exists())->toBeTrue();
    hostAs($office)->get(hostPath('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('hosts.shops.0.host', 'www.fitrahbooks.mv')->where('hosts.shops.0.status', 'active')->where('hosts.shop_host', 'shop.akuru.test'));

    // The domain now opens the shop on the canonical site; a path goes under it; a POST there does nothing.
    $this->get('http://www.fitrahbooks.mv/')->assertRedirect('https://akuru.test/shop/fitrah');
    $this->get('http://www.fitrahbooks.mv/about?x=1')->assertRedirect('https://akuru.test/shop/fitrah/about?x=1');
    $this->post('http://www.fitrahbooks.mv/')->assertNotFound();

    // Changing the domain puts it back to waiting; a suspended shop's domain is not answered; clearing removes it.
    $fitrah->update(['status' => 'suspended']);
    \Illuminate\Support\Facades\Cache::flush();
    $this->get('http://www.fitrahbooks.mv/zz-none')->assertNotFound();
    $fitrah->update(['status' => 'active']);
    hostAs($owner)->post(hostPath('vendor.host.save'), ['custom_host' => 'fitrah.mv']);
    expect($fitrah->refresh()->custom_host_status)->toBe('requested');
    $this->get('http://www.fitrahbooks.mv/zz-none')->assertNotFound();
    $this->get('http://fitrah.mv/zz-none')->assertNotFound();
    hostAs($owner)->post(hostPath('vendor.host.save'), ['custom_host' => '']);
    expect($fitrah->refresh()->custom_host)->toBeNull()->and($fitrah->custom_host_status)->toBeNull();
});

it('sends the whole-shop subdomain to the same path under /shop, and leaves the main site alone', function () {
    hostShop();
    $this->get('http://shop.akuru.test/')->assertRedirect('https://akuru.test/shop');
    $this->get('http://shop.akuru.test/fitrah?sort=name')->assertRedirect('https://akuru.test/shop/fitrah?sort=name');
    hostAs()->get('http://akuru.test/shop/fitrah')->assertOk();

    config(['bookshop.hosts.redirect_status' => 301]);
    $this->get('http://shop.akuru.test/cart')->assertStatus(301)->assertRedirect('https://akuru.test/shop/cart');
});

it('shows dollar prices as a guide only when the office turns them on, at its rate', function () {
    [$fitrah] = hostShop();
    $office = hostOffice();
    $book = Product::query()->create(['vendor_id' => $fitrah->id, 'slug' => 'tracing-book', 'title' => 'Tracing Book', 'price' => 154.20, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    hostAs()->get(hostPath('public.shop.product', $book->slug))->assertOk()->assertDontSee('data-testid="product-usd"', false);

    hostAs(User::factory()->create())->post(hostPath('admin.bookshop.usd'), ['on' => 1, 'rate' => 15.42])->assertForbidden();
    hostAs($office)->post(hostPath('admin.bookshop.usd'), ['on' => 1, 'rate' => 0])->assertSessionHasErrors('rate');
    hostAs($office)->post(hostPath('admin.bookshop.usd'), ['on' => 1, 'rate' => 15.42])->assertSessionHasNoErrors();
    hostAs($office)->get(hostPath('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('usd.on', true)->where('usd.rate', 15.42));

    hostAs()->get(hostPath('public.shop.product', $book->slug))->assertSee('≈ USD 10.00')->assertSee(__('shop.usd_guide_note'));
    hostAs()->get(hostPath('public.shop.vendor', 'fitrah'))->assertSee('data-testid="card-usd"', false)->assertSee('≈ USD 10.00');
    $customer = User::factory()->create();
    hostAs($customer)->post(hostPath('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 2]);
    hostAs($customer)->get(hostPath('public.shop.cart'))->assertSee('≈ USD 20.00')->assertSee(__('shop.usd_charged_in_mvr'));

    hostAs($office)->post(hostPath('admin.bookshop.usd'), ['on' => 0, 'rate' => 15.42]);
    hostAs()->get(hostPath('public.shop.product', $book->slug))->assertDontSee('≈ USD');
});
