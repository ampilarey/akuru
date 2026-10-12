<?php

use App\Domains\Bookshop\Actions\Money\RecordVendorEarningAction;
use App\Domains\Bookshop\Actions\Print\SavePrintSettingsAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The print desk's office (NAME_TAGS_PLAN §4, §11 NT1, STATUS §5ro): the
 * papers, finishing, layouts and subject sets the desk works from, its
 * settings, and the Akuru vendor its products belong to.
 */
uses(RefreshDatabase::class);

it('makes the Akuru vendor, the Print category and a draft product once, and seeds the layouts and unpriced papers', function () {
    expect(PrintDesk::vendor())->toBeNull();
    PrintDesk::ensure();
    PrintDesk::ensure();
    $vendor = PrintDesk::vendor();
    expect(Vendor::query()->where('kind', 'akuru')->count())->toBe(1)->and(Product::query()->count())->toBe(1)
        ->and($vendor->isAkuru())->toBeTrue()
        ->and($vendor->fulfilment)->toBe('akuru')
        ->and($vendor->deliveryMethods()->where('kind', 'collect_akuru')->exists())->toBeTrue();

    $product = PrintDesk::product();
    expect($product->vendor_id)->toBe($vendor->id)
        ->and($product->status->value)->toBe('draft')
        ->and($product->track_stock)->toBeFalse()
        ->and($product->title_dv)->toMatch('/\p{Thaana}/u');

    expect(PrintLayout::query()->count())->toBe(5)
        ->and(PrintLayout::query()->get()->every(fn (PrintLayout $l) => $l->fitsA4()))->toBeTrue()
        ->and(PrintLayout::query()->where('sheet_code', 'L7160')->first()->tagsPerSheet())->toBe(21)
        ->and(PrintPaperOption::query()->count())->toBe(4)
        ->and(PrintPaperOption::query()->where('is_active', true)->exists())->toBeFalse()
        ->and(PrintSubjectSet::query()->count())->toBe(3)
        ->and(PrintSubjectSet::query()->first()->subjects[0]['dv'])->toBe('ދިވެހި');
});

it('opens the office for the Bookstore manager only, with every list and the reason the service is off', function () {
    printWeb()->actingAs(User::factory()->create())->get(route('admin.bookshop.print'))->assertForbidden();

    printWeb()->actingAs(printOffice())->get(route('admin.bookshop.print'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Bookshop/Print')
            ->where('settings.enabled', false)->where('can_enable', false)
            ->where('cannot_enable_because', __('print.error_enable_no_chrome'))
            ->where('product.status', 'draft')->has('papers', 4)->has('layouts', 5)->has('subject_sets', 3)->has('finishing', 4)->has('t'));
});

it('refuses to switch the service on without a Chrome, then without a priced paper, and puts the product on sale when it can', function () {
    $office = printOffice();
    app()->setLocale('dv');
    $post = fn (array $data = []) => printWeb()->actingAs($office)->from(route('admin.bookshop.print'))
        ->post(route('admin.bookshop.print.settings'), $data + ['enabled' => true, 'lead_days' => 3, 'min_sheets' => 2, 'tiers' => [['sheets' => 10, 'percent' => 5], ['sheets' => 50, 'percent' => 10]]]);

    $post()->assertSessionHasErrors(['enabled' => __('print.error_enable_no_chrome', [], 'dv')]);
    expect(PrintDesk::settings()['enabled'])->toBeFalse();

    printHostCanPrint();
    $post()->assertSessionHasErrors(['enabled' => __('print.error_enable_no_paper', [], 'dv')]);

    PrintPaperOption::query()->first()->update(['price_bw' => 2.5, 'is_active' => true]);
    $post()->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_settings_saved', [], 'dv'));
    $settings = PrintDesk::settings();
    expect($settings['enabled'])->toBeTrue()->and($settings['lead_days'])->toBe(3)->and($settings['min_sheets'])->toBe(2)
        ->and($settings['tiers'])->toBe([['sheets' => 10, 'percent' => 5.0], ['sheets' => 50, 'percent' => 10.0]])
        ->and(PrintDesk::product()->fresh()->status->value)->toBe('active');

    // Off again: the product leaves the shop.
    $post(['enabled' => false])->assertSessionHasNoErrors();
    expect(PrintDesk::settings()['enabled'])->toBeFalse()->and(PrintDesk::product()->fresh()->status->value)->toBe('draft');
});

it('prices a paper, refuses an active one at zero in Dhivehi, and retires rather than deletes', function () {
    $office = printOffice();
    app()->setLocale('dv');
    $route = fn (?int $id = null) => route('admin.bookshop.print.paper', $id);
    $paper = ['name' => 'Pearl card', 'name_dv' => 'ޕާލް ކާޑު', 'name_ar' => 'ورق لؤلؤي', 'gsm' => 250, 'kind' => 'card', 'price_bw' => 0, 'price_colour' => 0, 'is_active' => true];

    printWeb()->actingAs($office)->from(route('admin.bookshop.print'))->post($route(), $paper)
        ->assertSessionHasErrors(['price_bw' => __('print.error_paper_price', [], 'dv')]);
    printWeb()->actingAs($office)->post($route(), ['kind' => 'velvet'] + $paper)->assertSessionHasErrors('kind');
    printWeb()->actingAs($office)->post($route(), ['price_colour' => 4] + $paper)->assertSessionHasNoErrors();

    $row = PrintPaperOption::query()->where('name', 'Pearl card')->firstOrFail();
    expect((string) $row->price_colour)->toBe('4.00')->and($row->is_active)->toBeTrue()->and($row->nameIn('dv'))->toBe('ޕާލް ކާޑު');

    printWeb()->actingAs($office)->post($route($row->id), ['is_active' => false, 'price_colour' => 4] + $paper)->assertSessionHasNoErrors();
    expect($row->fresh()->is_active)->toBeFalse()->and(PrintPaperOption::query()->whereKey($row->id)->exists())->toBeTrue();
    // There is no route that deletes one.
    expect(collect(app('router')->getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_starts_with($n, 'admin.bookshop.print') && str_contains($n, 'delete'))->all())->toBe([]);
});

it('keeps a layout only when its grid fits an A4 sheet, and names the footprint when it does not', function () {
    $office = printOffice();
    app()->setLocale('ar');
    $grid = ['name' => 'Wide', 'columns' => 3, 'rows' => 4, 'tag_w_mm' => 80, 'tag_h_mm' => 60, 'margin_top_mm' => 10, 'margin_left_mm' => 5, 'gutter_x_mm' => 5, 'gutter_y_mm' => 5];

    printWeb()->actingAs($office)->from(route('admin.bookshop.print'))->post(route('admin.bookshop.print.layout'), $grid)
        ->assertSessionHasErrors(['columns' => __('print.error_layout_fits', ['width' => 255, 'height' => 265], 'ar')]);
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), ['tag_w_mm' => 5] + $grid)->assertSessionHasErrors('tag_w_mm');
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), ['tag_w_mm' => 60] + $grid)->assertSessionHasNoErrors();
    expect(PrintLayout::query()->where('name', 'Wide')->first()->tagsPerSheet())->toBe(12);
});

it('pairs a subject set\'s three lists line by line, and refuses lists of different lengths', function () {
    $office = printOffice();
    $set = ['label' => 'Grade 4', 'label_dv' => 'ގްރޭޑް 4', 'label_ar' => 'الصف الرابع', 'subjects_en' => "Dhivehi\nEnglish\n", 'subjects_dv' => "ދިވެހި\nއިނގިރޭސި", 'subjects_ar' => "الديفيهية\nالإنجليزية\nالرياضيات"];

    printWeb()->actingAs($office)->from(route('admin.bookshop.print'))->post(route('admin.bookshop.print.subject-set'), $set)
        ->assertSessionHasErrors(['subjects_en' => __('print.error_subjects_count', ['en' => 2, 'dv' => 2, 'ar' => 3])]);
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.subject-set'), ['subjects_ar' => "الديفيهية\nالإنجليزية"] + $set)->assertSessionHasNoErrors();
    // MySQL 8 stores a JSON object's keys in its own order; compare the values, not the order.
    $subjects = PrintSubjectSet::query()->where('label', 'Grade 4')->first()->subjects;
    expect(array_map(fn (array $s) => [$s['en'], $s['dv'], $s['ar']], $subjects))->toBe([
        ['Dhivehi', 'ދިވެހި', 'الديفيهية'], ['English', 'އިނގިރޭސި', 'الإنجليزية'],
    ]);
});

it('records no earning on an order of the Akuru vendor: its sales are Akuru\'s whole', function () {
    [$vendor] = PrintDesk::ensure();
    $customer = User::factory()->create();
    $checkout = BookshopCheckout::query()->create(['number' => 'CHK-NT1', 'user_id' => $customer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'subtotal' => 220, 'total' => 220, 'currency' => 'MVR', 'address_snapshot' => [], 'paid_at' => now()]);
    $order = Order::query()->create([
        'number' => 'AKU-NT1-1', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $customer->id, 'status' => 'paid', 'delivery_kind' => 'collect_akuru', 'delivery_name' => 'Collect',
        'delivery_fee' => 0, 'fulfilled_by' => 'akuru', 'subtotal' => 120, 'discount' => 0, 'tax' => 0, 'total' => 120, 'currency' => 'MVR', 'paid_at' => now(), 'address_snapshot' => [],
    ]);

    expect(app(RecordVendorEarningAction::class)->execute($order))->toBeNull()
        ->and(VendorEarning::query()->where('order_id', $order->id)->exists())->toBeFalse();

    // A shop's order still earns.
    $shop = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'commission_rate' => 10]);
    $theirs = Order::query()->create(['number' => 'FIT-NT1-1', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $shop->id, 'user_id' => $customer->id, 'status' => 'paid', 'delivery_kind' => 'collect_vendor', 'delivery_name' => 'Collect',
        'delivery_fee' => 0, 'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100, 'currency' => 'MVR', 'paid_at' => now(), 'address_snapshot' => []]);
    expect(app(RecordVendorEarningAction::class)->execute($theirs))->not->toBeNull();
});

it('exports each list as CSV and names its refusals in three languages, none English in the actions', function () {
    $office = printOffice();
    foreach (['papers' => 'name,name_dv', 'finishing' => 'cuts_sheet', 'layouts' => 'tag_w_mm', 'subject_sets' => 'subjects'] as $list => $needle) {
        $csv = printWeb()->actingAs($office)->get(route('admin.bookshop.print.export', ['list' => $list]))->assertOk()->streamedContent();
        expect($csv)->toContain($needle);
    }
    printWeb()->actingAs($office)->get(route('admin.bookshop.print.export', ['list' => 'orders']))->assertSessionHasErrors('list');

    foreach (glob(app_path('Domains/Bookshop/Actions/Print/*.php')) as $file) {
        expect(refusalEnglishIn(str_replace(base_path().'/', '', $file)))->toBe([], basename($file));
    }
    foreach (array_keys(require base_path('resources/lang/en/print.php')) as $key) {
        expect((require base_path('resources/lang/dv/print.php'))[$key] ?? '')->toMatch('/\p{Thaana}|CSV|KB|gsm|^[0-9]+$/u', $key)
            ->and((require base_path('resources/lang/ar/print.php'))[$key] ?? '')->toMatch('/\p{Arabic}|CSV|^[0-9]+$/u', $key);
    }
    expect(fn () => app(SavePrintSettingsAction::class)->execute(['enabled' => true, 'lead_days' => 1, 'min_sheets' => 1]))->toThrow(ValidationException::class);
});
