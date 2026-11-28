<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Support\OfficeKnobs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ST4 (admin audit 2026-11-27, STATUS §5uw): the business settings that
 * lived in `.env` are changed on a screen, a page per product. The office's
 * value is laid over the deploy's at boot, so every existing `config()`
 * reader sees it; the deploy's stays the default shown beside the field.
 */
uses(RefreshDatabase::class);

it('shows a product its own settings, grouped, with what is in force and the server\'s value', function () {
    config(['lending.default_days' => 14, 'lending.max_days' => 60]);
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['bookshop.manage', 'commerce.manage']));

    $web->get('/admin/settings/office/lending')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Knobs')
        ->where('product', 'lending')->where('title', 'Lending settings')
        ->where('groups', fn ($groups) => collect($groups)->pluck('key')->all() === ['loans', 'notices']
            && collect($groups[0]['knobs'])->pluck('name')->all() === ['default_days', 'max_days', 'max_books', 'free_taken_days']
            && $groups[0]['knobs'][0]['value'] === 14 && $groups[0]['knobs'][0]['default'] === 14
            && collect($groups)->flatMap(fn ($g) => $g['knobs'])->every(fn ($k) => ! str_starts_with($k['label'], 'knobs.') && ! str_starts_with($k['hint'], 'knobs.')))
        ->has('t.save')->has('admin_t.settings_all'));
    // The wallet's are the wallet's alone.
    $web->get('/admin/settings/office/wallet')->assertOk()->assertInertia(fn (Assert $page) => $page->where('product', 'wallet')
        ->where('groups', fn ($groups) => collect($groups)->flatMap(fn ($g) => collect($g['knobs'])->pluck('name'))->all() === ['alert_credit_at']));
});

it('saves a group, puts it in force at once and at the next boot, and keeps the server\'s value as the default', function () {
    config(['lending.default_days' => 14, 'lending.max_days' => 60, 'lending.notices.sms' => true]);
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['bookshop.manage']));

    $web->post('/admin/settings/office/lending', ['group' => 'loans', 'values' => ['default_days' => '21', 'max_days' => '90', 'max_books' => '10', 'free_taken_days' => '7']])->assertSessionHasNoErrors();
    expect(config('lending.default_days'))->toBe(21)->and(config('lending.max_days'))->toBe(90)->and(config('lending.max_books'))->toBe(10);
    // A switch left unticked is off.
    $web->post('/admin/settings/office/lending', ['group' => 'notices', 'values' => ['remind_days_before' => '3', 'notices_email' => true]])->assertSessionHasNoErrors();
    expect(config('lending.notices.sms'))->toBeFalse()->and(config('lending.notices.email'))->toBeTrue()->and(config('lending.reminders.days_before'))->toBe(3);

    // A new request starts from the deploy's values and is given the office's.
    config(['lending.default_days' => 14, 'lending.max_days' => 60, 'lending.notices.sms' => true]);
    OfficeKnobs::apply();
    expect(config('lending.default_days'))->toBe(21)->and(config('lending.max_days'))->toBe(90)->and(config('lending.notices.sms'))->toBeFalse();
    $web->get('/admin/settings/office/lending')->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.knobs.0.value', 21)->where('groups.0.knobs.0.default', 14)->where('groups.1.knobs.2.value', false)->where('groups.1.knobs.2.default', true));
});

it('refuses a value out of bounds, a usual loan longer than the longest, and a group that is not the page\'s', function () {
    config(['lending.default_days' => 14, 'lending.max_days' => 60]);
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['bookshop.manage']));

    $web->post('/admin/settings/office/lending', ['group' => 'loans', 'values' => ['default_days' => '0', 'max_days' => '9999', 'max_books' => 'many', 'free_taken_days' => '1.5']])
        ->assertSessionHasErrors(['default_days', 'max_days', 'max_books', 'free_taken_days']);
    $web->post('/admin/settings/office/lending', ['group' => 'loans', 'values' => ['default_days' => '61']])->assertSessionHasErrors('default_days');
    $web->post('/admin/settings/office/lending', ['group' => 'loans', 'values' => ['default_days' => '30', 'max_days' => '20']])->assertSessionHasErrors('default_days');
    $web->post('/admin/settings/office/lending', ['group' => 'alerts', 'values' => ['alert_credit_at' => '5']])->assertSessionHasErrors('group');
    expect(config('lending.default_days'))->toBe(14)->and(DB::table('settings')->where('key', 'like', 'knob.%')->count())->toBe(0);

    // A stored value edited by hand to nonsense is ignored, not trusted.
    DB::table('settings')->insert(['key' => 'knob.lending.max_days', 'value' => '-4', 'type' => 'string', 'group' => 'knobs', 'created_at' => now(), 'updated_at' => now()]);
    Cache::flush();
    OfficeKnobs::apply();
    expect(config('lending.max_days'))->toBe(60);
});

it('opens each page only to its own office, and lists it on the Settings index for them', function () {
    \Spatie\Permission\Models\Role::findOrCreate('bookshop_manager', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('bookshop.manage', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('bookshop_manager');
    $manager->givePermissionTo('bookshop.manage');
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs(User::factory()->create())->get('/admin/settings/office/lending')->assertForbidden();
    $web->actingAs($manager)->get('/admin/settings/office/lending')->assertOk();
    $web->actingAs($manager)->get('/admin/settings/office/wallet')->assertForbidden();
    $web->actingAs($manager)->post('/admin/settings/office/wallet', ['group' => 'alerts', 'values' => ['alert_credit_at' => '5']])->assertForbidden();
    $web->actingAs($manager)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
        ->where('cards', fn ($cards) => collect($cards)->pluck('key')->contains('lending_settings') && ! collect($cards)->pluck('key')->contains('wallet_settings')));

    config(['commerce.wallet.alert_credit_at' => 1000.0]);
    $office = actingSystemAdmin(['commerce.manage']);
    $web->actingAs($office)->post('/admin/settings/office/wallet', ['group' => 'alerts', 'values' => ['alert_credit_at' => '250.50']])->assertSessionHasNoErrors();
    expect(config('commerce.wallet.alert_credit_at'))->toBe(250.5);
    $web->actingAs($office)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('cards', fn ($cards) => collect($cards)->pluck('key')->contains('wallet_settings')));
});

it('keeps the Bookstore\'s money, tax and bank to the system admin, and its rules to its office (ST4b)', function () {
    \Spatie\Permission\Models\Role::findOrCreate('bookshop_manager', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('bookshop.manage', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('bookshop_manager');
    $manager->givePermissionTo('bookshop.manage');
    config(['bookshop.checkout.reservation_minutes' => 30, 'bookshop.default_commission_rate' => 10.0, 'bookshop.bank_transfer.account_number' => '7730000000001']);
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($manager)->post('/admin/settings/office/bookstore', ['group' => 'checkout', 'values' => ['reservation_minutes' => '45', 'cart_reminders' => true]])->assertSessionHasNoErrors();
    expect(config('bookshop.checkout.reservation_minutes'))->toBe(45);
    $web->actingAs($manager)->get('/admin/settings/office/bookstore_money')->assertForbidden();
    $web->actingAs($manager)->post('/admin/settings/office/bookstore_money', ['group' => 'bank', 'values' => ['bank_name' => 'B', 'bank_account_name' => 'Mine', 'bank_account_number' => '1']])->assertForbidden();
    expect(config('bookshop.bank_transfer.account_number'))->toBe('7730000000001');

    $office = actingSystemAdmin(['bookshop.manage']);
    $web->actingAs($office)->get('/admin/settings/office/bookstore_money')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->pluck('key')->all() === ['commission', 'tax', 'bank']
            && collect($groups)->flatMap(fn ($g) => $g['knobs'])->every(fn ($k) => ! str_starts_with($k['label'], 'knobs.') && ! str_starts_with($k['hint'], 'knobs.'))));
    $web->actingAs($office)->post('/admin/settings/office/bookstore_money', ['group' => 'commission', 'values' => ['default_commission_rate' => '12.5', 'payouts_enabled' => true, 'min_payout' => '250']])->assertSessionHasNoErrors();
    $web->actingAs($office)->post('/admin/settings/office/bookstore_money', ['group' => 'tax', 'values' => ['tax_standard' => '8', 'commission_tax_rate' => '8', 'issuer_gst_registered' => true, 'issuer_name' => 'Akuru Institute Pvt Ltd', 'issuer_tin' => '']])->assertSessionHasNoErrors();
    $web->actingAs($office)->post('/admin/settings/office/bookstore_money', ['group' => 'bank', 'values' => ['bank_name' => 'Bank of Maldives', 'bank_account_name' => 'Akuru Institute', 'bank_account_number' => '7730000000999']])->assertSessionHasNoErrors();
    expect(config('bookshop.default_commission_rate'))->toBe(12.5)->and(config('bookshop.money.min_payout'))->toBe(250.0)
        ->and(config('bookshop.money.issuer_name'))->toBe('Akuru Institute Pvt Ltd')->and(config('bookshop.money.issuer_tin'))->toBeNull()
        ->and(config('bookshop.money.issuer_gst_registered'))->toBeTrue()
        ->and(config('bookshop.bank_transfer.account_number'))->toBe('7730000000999');

    // A rate past its bound, a name left empty and one too long are refused.
    $web->actingAs($office)->post('/admin/settings/office/bookstore_money', ['group' => 'commission', 'values' => ['default_commission_rate' => '80', 'min_payout' => '-1']])->assertSessionHasErrors(['default_commission_rate', 'min_payout']);
    $web->actingAs($office)->post('/admin/settings/office/bookstore_money', ['group' => 'tax', 'values' => ['issuer_name' => '', 'issuer_tin' => str_repeat('9', 41)]])->assertSessionHasErrors(['issuer_name', 'issuer_tin']);
    expect(config('bookshop.default_commission_rate'))->toBe(12.5)->and(config('bookshop.money.issuer_name'))->toBe('Akuru Institute Pvt Ltd');
});

it('sets the student blog rules, and takes only a safe address for the guidelines (ST4b)', function () {
    config(['library.blog.teacher_review' => true, 'library.blog.posts_per_week' => 3, 'library.blog.guidelines_url' => null]);
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['library.manage']));

    $web->get('/admin/settings/office/blogs')->assertOk()->assertInertia(fn (Assert $page) => $page->where('groups.0.knobs.2.value', '')->where('groups.0.knobs.2.type', 'url'));
    foreach (['javascript:alert(1)', '//evil.example/x', 'ftp://x.example', 'https://x.example/<script>'] as $bad) {
        $web->post('/admin/settings/office/blogs', ['group' => 'students', 'values' => ['posts_per_week' => '3', 'guidelines_url' => $bad]])->assertSessionHasErrors('guidelines_url');
    }
    expect(config('library.blog.guidelines_url'))->toBeNull();

    $web->post('/admin/settings/office/blogs', ['group' => 'students', 'values' => ['posts_per_week' => '5', 'guidelines_url' => '/page/blog-guidelines']])->assertSessionHasNoErrors();
    expect(config('library.blog.teacher_review'))->toBeFalse()->and(config('library.blog.posts_per_week'))->toBe(5)->and(config('library.blog.guidelines_url'))->toBe('/page/blog-guidelines');
    $web->post('/admin/settings/office/blogs', ['group' => 'students', 'values' => ['teacher_review' => true, 'posts_per_week' => '5', 'guidelines_url' => '']])->assertSessionHasNoErrors();
    expect(config('library.blog.teacher_review'))->toBeTrue()->and(config('library.blog.guidelines_url'))->toBeNull();
});
