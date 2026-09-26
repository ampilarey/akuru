<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Models\VendorStorefrontVersion;
use App\Domains\Bookshop\Support\Theme;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B4, the storefront designer part 1: a vendor gives
 * its page an identity (logo, banner, story, contact, hours, socials) and
 * a theme (a preset or its own palette, fonts, scale, shape, dark mode),
 * previews the draft on the real renderer, publishes — only when every
 * colour pair reads — and rolls back to an earlier version. The public
 * page stays the plain one until something is published.
 */
function designShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => $slug, 'code' => 'FIT', 'tagline' => 'iman.noor.ihsan', 'status' => 'active']);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'tracing-book', 'title' => 'Tracing Book', 'price' => 85, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 5, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $owner, $staff];
}

function designAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function fitrahPalette(): array
{
    // The vendor kit's three brand colours, with a readable text on the dusty blue and a darker coral for links.
    return ['primary' => '#7B9AA5', 'secondary' => '#F2C778', 'accent' => '#B0553F', 'page_bg' => '#FBF7F1', 'card_bg' => '#FFFFFF', 'text' => '#2F3A40', 'on_primary' => '#1A2226', 'on_accent' => '#FFFFFF'];
}

function draftInput(array $overrides = []): array
{
    return array_replace_recursive([
        'theme' => ['preset' => 'ocean', 'fonts' => ['heading' => 'Bree Serif', 'body' => 'Inter', 'accent' => 'Courier Prime', 'dhivehi' => 'Faruma', 'arabic' => 'Noto Naskh Arabic'], 'scale' => 'regular', 'shape' => ['radius' => 'soft', 'button' => 'filled', 'card' => 'flat', 'banner_height' => 'regular', 'image_ratio' => 'square']],
        'name_dv' => 'ފިތުރަތު', 'tagline_dv' => 'އީމާން ނޫރު އިޙްސާން',
        'story' => "We make learning materials for little hands.\n\nEvery item is tested with our own children.<script>alert(1)</script>",
        'story_dv' => 'ކުޑަކުދިންނަށް',
        'contact' => ['phone' => '7920288', 'email' => 'hello@example.test', 'viber' => '7920288', 'address' => 'Majeedhee Magu, Malé', 'map_url' => 'https://maps.app.goo.gl/abc'],
        'hours' => "Sat–Thu 10:00–22:00\nFri 16:00–22:00",
        'socials' => ['instagram' => 'instagram.com/fitrah.mv', 'facebook' => 'https://evil.example/fitrah', 'tiktok' => 'javascript:alert(1)'],
    ], $overrides);
}

it('opens the designer with the defaults, every choice on offer and Akuru\'s palette locked without the badge', function () {
    [$vendor, $owner, $staff] = designShop();

    designAs($staff)->get(route('vendor.storefront.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Bookshop/VendorStorefront')
        ->where('designer.exists', false)
        ->where('designer.theme.preset', 'ocean')
        ->where('designer.theme.colors.primary', '#0F4C81')
        ->where('designer.theme.fonts.dhivehi', 'Faruma')
        ->where('designer.problems', [])
        ->where('designer.versions', [])
        ->where('designer.options.presets.akuru.locked', true)
        ->where('designer.options.presets.ocean.locked', false)
        ->where('designer.options.fonts.dhivehi', ['Faruma', 'Noto Sans Thaana'])
        ->where('designer.options.fonts.latin.4', 'Bree Serif')
        ->has('designer.options.shapes.radius', 3));
    designAs(User::factory()->create())->get(route('vendor.storefront.index'))->assertForbidden();

    // Every preset reads, and so does the derived dark scheme.
    foreach (array_keys((array) config('bookshop.storefront.presets')) as $key) {
        $theme = Theme::default();
        $theme['colors'] = Theme::preset($key)['colors'];
        $theme['dark'] = ['enabled' => true, 'colors' => Theme::derivedDark($theme['colors'])];
        expect(Theme::problems($theme))->toBe([], $key);
    }
});

it('saves a draft whose colours fail to read, says which pairs and why, and refuses to publish it', function () {
    [$vendor, $owner] = designShop();
    // The kit's first proposal: cream on dusty blue, coral links on cream.
    $failing = fitrahPalette();
    $failing['on_primary'] = '#FBF7F1';
    $failing['accent'] = '#EBAD99';
    $failing['on_accent'] = '#2F3A40';

    designAs($owner)->post(route('vendor.storefront.draft'), draftInput(['theme' => ['preset' => '', 'colors' => $failing]]))
        ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '2'));

    designAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page
        ->where('designer.exists', true)
        ->where('designer.theme.preset', null)
        ->has('designer.problems', 2)
        ->where('designer.problems.0.pair', 'on_primary/primary')
        ->where('designer.problems.0.needed', 4.5)
        ->where('designer.problems.1.pair', 'accent/page_bg')
        ->where('designer.published_at', null));

    designAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasErrors('theme');
    expect(VendorStorefront::query()->firstOrFail()->published_at)->toBeNull()->and(VendorStorefrontVersion::query()->count())->toBe(0);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertDontSee('data-testid="storefront"', false);

    // The kit's palette with a readable text on the band and a darker coral passes.
    designAs($owner)->post(route('vendor.storefront.draft'), draftInput(['theme' => ['preset' => '', 'colors' => fitrahPalette()]]))->assertSessionHasNoErrors();
    designAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->where('designer.problems', []));
});

it('previews the draft on the real page, publishes it as version 1, and the public page wears the theme and identity', function () {
    Storage::fake('public');
    [$vendor, $owner, $staff] = designShop();

    designAs($staff)->post(route('vendor.storefront.draft'), draftInput() + ['images' => ['logo' => UploadedFile::fake()->image('logo.jpg', 800, 800), 'banner' => UploadedFile::fake()->image('banner.jpg', 2400, 800)]])
        ->assertRedirect()->assertSessionHasNoErrors();

    $draft = VendorStorefront::query()->firstOrFail()->draft_identity;
    expect($draft['images']['logo'])->toBeInt()->and($draft['images']['banner'])->toBeInt()->and($draft['images']['logo_dark'])->toBeNull()
        ->and($draft['story'])->toContain('<p>We make learning materials for little hands.</p>')->not->toContain('<script')
        ->and($draft['socials']['instagram'])->toBe('https://instagram.com/fitrah.mv')
        ->and($draft['socials']['facebook'])->toBeNull()
        ->and($draft['socials']['tiktok'])->toBeNull()
        ->and($draft['contact']['map_url'])->toBe('https://maps.app.goo.gl/abc');

    // The preview is the public renderer on the draft, for members only; the public page is still plain.
    $preview = designAs($staff)->get(route('vendor.storefront.preview'))->assertOk();
    $preview->assertSee('data-testid="storefront"', false)->assertSee('data-preview="1"', false)->assertSee('data-testid="preview-banner"', false)
        ->assertSee('--sf-primary: #0F4C81', false)->assertSee('We make learning materials')->assertSee('instagram.com/fitrah.mv')->assertSee('7920288');
    designAs(User::factory()->create())->get(route('vendor.storefront.preview'))->assertForbidden();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertDontSee('data-testid="storefront"', false)->assertSee('iman.noor.ihsan');

    // Staff cannot publish; the owner can, with a note.
    designAs($staff)->post(route('vendor.storefront.publish'), ['note' => 'First look'])->assertForbidden();
    designAs($owner)->post(route('vendor.storefront.publish'), ['note' => 'First look'])->assertRedirect()->assertSessionHasNoErrors();
    $storefront = VendorStorefront::query()->firstOrFail();
    $version = VendorStorefrontVersion::query()->firstOrFail();
    expect($storefront->published_at)->not->toBeNull()->and($storefront->published_version_id)->toBe($version->id)
        ->and($version->number)->toBe(1)->and($version->note)->toBe('First look')->and($version->created_by)->toBe($owner->id);

    $public = test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk();
    $public->assertSee('data-testid="storefront"', false)->assertSee('data-preview="0"', false)->assertSee('data-preset="ocean"', false)
        ->assertSee('--sf-primary: #0F4C81', false)->assertSee("--sf-font-heading: 'Bree Serif', 'Faruma'", false)
        ->assertSee('fonts.googleapis.com/css2?family=Bree+Serif', false)->assertDontSee('family=Faruma', false)
        ->assertSee('data-testid="storefront-logo"', false)->assertSee('-w240.webp', false)->assertSee('data-testid="storefront-banner"', false)->assertSee('-w1600.webp', false)
        ->assertSee('iman.noor.ihsan')->assertSee('at Akuru Bookstore')->assertSee('We make learning materials')->assertSee('Sat–Thu 10:00–22:00')
        ->assertSee('https://instagram.com/fitrah.mv', false)->assertDontSee('evil.example')->assertDontSee('<script')
        ->assertSee('data-product="tracing-book"', false)->assertDontSee('data-testid="preview-banner"', false)->assertDontSee('data-testid="badge-', false);

    // In Dhivehi the name and story are the Dhivehi ones.
    \Illuminate\Support\Facades\App::setLocale('dv');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertSee('ފިތުރަތު')->assertSee('ކުޑަކުދިންނަށް')->assertSee('އީމާން ނޫރު އިޙްސާން');
    \Illuminate\Support\Facades\App::setLocale('en');
});

it('keeps versions to roll back to, and the office\'s badges unlock Akuru\'s palette and show on the page', function () {
    [$vendor, $owner] = designShop();
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    designAs($owner)->post(route('vendor.storefront.draft'), draftInput());
    designAs($owner)->post(route('vendor.storefront.publish'), ['note' => 'Ocean']);
    designAs($owner)->post(route('vendor.storefront.draft'), draftInput(['theme' => ['preset' => 'forest']]));
    designAs($owner)->post(route('vendor.storefront.publish'));
    $v1 = VendorStorefrontVersion::query()->where('number', 1)->firstOrFail();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('--sf-primary: #1F5F3F', false);

    designAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page
        ->has('designer.versions', 2)->where('designer.versions.0.number', 2)->where('designer.versions.0.live', true)->where('designer.versions.1.note', 'Ocean')->where('designer.draft_dirty', false));

    designAs($owner)->post(route('vendor.storefront.roll-back', $v1->id))->assertRedirect()->assertSessionHasNoErrors();
    $storefront = VendorStorefront::query()->firstOrFail();
    $v3 = VendorStorefrontVersion::query()->where('number', 3)->firstOrFail();
    expect($storefront->published_version_id)->toBe($v3->id)->and($v3->note)->toBe('Rolled back to v1')
        ->and($storefront->published_theme['colors']['primary'])->toBe('#0F4C81')->and($storefront->draft_theme['colors']['primary'])->toBe('#0F4C81');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('--sf-primary: #0F4C81', false);
    designAs($owner)->post(route('vendor.storefront.roll-back', 999))->assertNotFound();

    // Akuru's palette is refused without the badge; the office grants it and it goes through.
    designAs($owner)->post(route('vendor.storefront.draft'), draftInput(['theme' => ['preset' => 'akuru']]))->assertSessionHasErrors('theme');
    designAs($office)->put(route('admin.bookshop.vendors.update', $vendor->id), ['name' => 'Fitrah', 'status' => 'active', 'badges' => ['bogus']])->assertSessionHasErrors('badges.0');
    designAs($office)->put(route('admin.bookshop.vendors.update', $vendor->id), ['name' => 'Fitrah', 'status' => 'active', 'badges' => ['akuru_partner', 'verified']])->assertRedirect()->assertSessionHasNoErrors();
    expect($vendor->refresh()->badges)->toBe(['verified', 'akuru_partner']);
    designAs($owner)->get(route('vendor.storefront.index'))->assertInertia(fn ($page) => $page->where('designer.options.presets.akuru.locked', false));
    designAs($owner)->post(route('vendor.storefront.draft'), draftInput(['theme' => ['preset' => 'akuru']]))->assertSessionHasNoErrors();
    designAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()
        ->assertSee('data-preset="akuru"', false)->assertSee('data-testid="badge-verified"', false)->assertSee('data-testid="badge-akuru_partner"', false)->assertSee('Akuru partner');
});

it('adds a derived dark scheme when asked, keeps only allowed fonts and shapes, and edits away from a preset', function () {
    [$vendor, $owner] = designShop();

    designAs($owner)->post(route('vendor.storefront.draft'), draftInput([
        'theme' => ['preset' => 'sand', 'colors' => ['accent' => '#B0413E'], 'fonts' => ['heading' => 'Comic Sans', 'dhivehi' => 'MV Waheed'], 'scale' => 'huge', 'shape' => ['radius' => 'round', 'button' => 'outlined', 'card' => 'shadow', 'banner_height' => 'tall', 'image_ratio' => 'portrait'], 'dark' => ['enabled' => 1]],
    ]))->assertSessionHasNoErrors();
    $theme = VendorStorefront::query()->firstOrFail()->draft_theme;
    expect($theme['preset'])->toBe('sand')
        ->and($theme['fonts']['heading'])->toBe('Inter')->and($theme['fonts']['dhivehi'])->toBe('Faruma')
        ->and($theme['scale'])->toBe('regular')->and($theme['shape']['radius'])->toBe('round')
        ->and($theme['dark']['enabled'])->toBeTrue()->and($theme['dark']['colors']['page_bg'])->toBe('#15151A')->and($theme['dark']['colors']['primary'])->toBe('#8A5A2B');

    designAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()
        ->assertSee('prefers-color-scheme: dark', false)->assertSee('--sf-page: #15151A', false)->assertSee('sf-outlined', false)->assertSee('--sf-radius: 1.25rem', false)->assertSee('--sf-image-ratio: 3 / 4', false);

    // A colour edited away from the preset makes it the vendor's own palette.
    designAs($owner)->post(route('vendor.storefront.draft'), draftInput(['theme' => ['preset' => 'sand', 'colors' => ['primary' => '#123456']]]));
    expect(VendorStorefront::query()->firstOrFail()->draft_theme['preset'])->toBeNull();
});
