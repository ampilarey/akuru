<?php

use App\Domains\Bookshop\Models\StorefrontTheme;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * B10d (ADR-039): the theme gallery — four starter looks from the office;
 * a shop applies one to its draft (its CSS, approved with the theme, going
 * live with the next publish); a shop offers its published look; the office
 * publishes (re-cleaning its CSS), declines with a note, or withdraws.
 */
function galleryShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function galleryAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

function galleryOffice(): User
{
    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web')->givePermissionTo('bookshop.manage');
    $office = User::factory()->create();
    $office->assignRole('admin');

    return $office;
}

it('offers four starter looks and applies one to the draft, its CSS going live with the publish', function () {
    [$fitrah, $owner] = galleryShop();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $fitrah->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    expect(StorefrontTheme::query()->where('status', 'published')->pluck('slug')->sort()->values()->all())->toBe(['classic-bookshop', 'evening-reading', 'modern-minimal', 'playful-kids']);

    galleryAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->has('gallery.themes', 4)->where('gallery.offered', null));
    $classic = StorefrontTheme::query()->where('slug', 'classic-bookshop')->sole();
    galleryAs($staff)->post(route('vendor.storefront.theme.apply', $classic->id))->assertForbidden();
    galleryAs($owner)->post(route('vendor.storefront.theme.apply', $classic->id))->assertSessionHasNoErrors();

    $storefront = VendorStorefront::query()->where('vendor_id', $fitrah->id)->sole();
    expect($storefront->draft_theme['fonts']['heading'])->toBe('Merriweather')->and($storefront->draft_theme['colors']['primary'])->toBe('#8A5A2B')
        ->and($storefront->custom_css_status)->toBe('theme')->and($storefront->custom_css_pending)->toContain('letter-spacing: .02em')->and($storefront->custom_css)->toBeNull()
        ->and($classic->refresh()->uses_count)->toBe(1);
    galleryAs($owner)->get(route('vendor.storefront.preview'))->assertSee('letter-spacing: .02em', false)->assertSee('Merriweather');

    // Publishing puts the look and its CSS live, no office step (the office approved the theme).
    galleryAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    expect($storefront->refresh()->custom_css)->toContain('letter-spacing: .02em')->and($storefront->custom_css_status)->toBe('approved');
    galleryAs()->get(route('public.shop.vendor', 'fitrah'))->assertSee('data-testid="shop-custom-css"', false)->assertSee('Merriweather');

    // A look without CSS clears an unpublished theme CSS but keeps the live one until the next publish replaces it.
    galleryAs($owner)->post(route('vendor.storefront.theme.apply', StorefrontTheme::query()->where('slug', 'playful-kids')->value('id')));
    galleryAs($owner)->post(route('vendor.storefront.theme.apply', StorefrontTheme::query()->where('slug', 'modern-minimal')->value('id')));
    expect($storefront->refresh()->custom_css_pending)->toBeNull()->and($storefront->custom_css_status)->toBe('approved')->and($storefront->draft_theme['scale'])->toBe('compact');
    galleryAs($owner)->post(route('vendor.storefront.theme.apply', 999999))->assertNotFound();
});

it('lets a shop offer its published look, and the office publish, decline or withdraw', function () {
    [$fitrah, $owner] = galleryShop();
    [$noor, $noorOwner] = galleryShop('noor');
    $office = galleryOffice();

    galleryAs($owner)->post(route('vendor.storefront.theme.offer'), ['name' => 'Fitrah warm'])->assertSessionHasErrors('name');
    galleryAs($owner)->post(route('vendor.storefront.draft'), ['theme' => ['preset' => 'forest', 'fonts' => ['heading' => 'Bree Serif']]])->assertSessionHasNoErrors();
    galleryAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    VendorStorefront::query()->where('vendor_id', $fitrah->id)->update(['custom_css' => '.storefront h2 { font-style: italic; }']);

    galleryAs($owner)->post(route('vendor.storefront.theme.offer'), ['name' => 'Fitrah warm', 'description' => 'Green and serif'])->assertSessionHasNoErrors();
    galleryAs($owner)->post(route('vendor.storefront.theme.offer'), ['name' => 'Again'])->assertSessionHasErrors('name');
    $offered = StorefrontTheme::query()->where('source_vendor_id', $fitrah->id)->sole();
    expect($offered->status)->toBe('submitted')->and($offered->theme['fonts']['heading'])->toBe('Bree Serif')->and($offered->custom_css)->toBe('.storefront h2 { font-style: italic; }');
    expect(UserNotification::query()->where('user_id', $office->id)->where('title', __('shop.notice_theme_offered_title'))->exists())->toBeTrue();
    galleryAs($noorOwner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->has('gallery.themes', 4)->where('gallery.offered', null));

    galleryAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('themes.waiting.0.name', 'Fitrah warm')->has('themes.gallery', 4));
    galleryAs($owner)->post(route('admin.bookshop.themes.decide', $offered->id), ['decision' => 'publish'])->assertForbidden();
    galleryAs($office)->post(route('admin.bookshop.themes.decide', $offered->id), ['decision' => 'decline'])->assertSessionHasErrors('note');
    galleryAs($office)->post(route('admin.bookshop.themes.decide', $offered->id), ['decision' => 'publish', 'name' => 'Forest serif'])->assertSessionHasNoErrors();
    expect($offered->refresh()->status)->toBe('published')->and($offered->name)->toBe('Forest serif');
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_theme_published_title'))->exists())->toBeTrue();

    // Noor applies Fitrah's look; its CSS goes live with Noor's publish.
    galleryAs($noorOwner)->post(route('vendor.storefront.theme.apply', $offered->id))->assertSessionHasNoErrors();
    galleryAs($noorOwner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    expect(VendorStorefront::query()->where('vendor_id', $noor->id)->value('custom_css'))->toBe('.storefront h2 { font-style: italic; }');

    // Withdrawn: gone from the gallery, Noor keeps its look.
    galleryAs($office)->post(route('admin.bookshop.themes.decide', $offered->id), ['decision' => 'withdraw'])->assertSessionHasNoErrors();
    galleryAs($noorOwner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->has('gallery.themes', 4));
    galleryAs($noorOwner)->post(route('vendor.storefront.theme.apply', $offered->id))->assertNotFound();
    expect(VendorStorefront::query()->where('vendor_id', $noor->id)->value('custom_css'))->toBe('.storefront h2 { font-style: italic; }');

    // A second offer after a decline.
    galleryAs($owner)->post(route('vendor.storefront.theme.offer'), ['name' => 'Fitrah two']);
    $second = StorefrontTheme::query()->where('name', 'Fitrah two')->sole();
    galleryAs($office)->post(route('admin.bookshop.themes.decide', $second->id), ['decision' => 'decline', 'note' => 'Too close to Forest serif'])->assertSessionHasNoErrors();
    galleryAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->where('gallery.offered.status', 'declined')->where('gallery.offered.note', 'Too close to Forest serif'));
});
