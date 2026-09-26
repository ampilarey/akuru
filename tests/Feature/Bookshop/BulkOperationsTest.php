<?php

use App\Domains\Bookshop\Mail\BookshopNoticeMail;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\StockMovement;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\SaveNotificationPreferencesAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B8, bulk and operations: the stock log at every
 * change, low-stock notices, the stock page, the product sheet in and out
 * (checked before it writes), duplicate and bulk status, paging for a shop
 * with hundreds of items, order exports by date and by line, and the email
 * and SMS switches behind every bookstore notice.
 */
function opsShop(string $slug = 'fitrah', array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active', 'contact_phone' => '7000001']);
    $owner = User::factory()->create(['email' => $slug.'-owner@example.test']);
    $staff = User::factory()->create(['email' => $slug.'-staff@example.test']);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);

    return [$vendor, $owner, $staff];
}

function opsProduct(Vendor $vendor, string $title, float $price, int $stock = 10, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function opsAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function opsCustomer(): User
{
    $customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($customer->id, 5000, 'admin', null, 'Top-up');

    return $customer;
}

function opsBuy(User $customer, Product $product, int $quantity): Order
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    $vendor = $product->vendor;
    opsAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'm'.VendorDeliveryMethod::query()->where('vendor_id', $vendor->id)->value('id')], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return Order::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
}

function opsOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function opsCsv(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'sheet').'.csv';
    file_put_contents($path, $content);

    return new UploadedFile($path, 'sheet.csv', 'text/csv', null, true);
}

function opsImport(User $who, string $csv): array
{
    $response = opsAs($who)->post(route('vendor.stock.import'), ['file' => opsCsv($csv)])->assertSessionHasNoErrors()->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    $token = (string) ($query['import'] ?? '');
    $page = opsAs($who)->get(route('vendor.stock.index', ['import' => $token]))->assertOk();

    return [$token, $page->viewData('page')['props']['import']];
}

it('logs every stock change: opening stock, an edit, a sale with its order, a cancellation put back — and never edits a line', function () {
    [$fitrah, $owner] = opsShop();
    opsAs($owner)->post(route('vendor.products.store'), ['title' => 'Tracing Book', 'price' => '85', 'tax_class' => 'zero_rated', 'status' => 'active', 'visibility' => 'shop', 'stock' => 12, 'track_stock' => 1])->assertSessionHasNoErrors();
    $book = Product::query()->where('title', 'Tracing Book')->sole();
    opsAs($owner)->post(route('vendor.products.update', $book->id), ['title' => 'Tracing Book', 'price' => '85', 'tax_class' => 'zero_rated', 'status' => 'active', 'visibility' => 'shop', 'stock' => 10, 'track_stock' => 1])->assertSessionHasNoErrors();

    $order = opsBuy(opsCustomer(), $book->refresh(), 3);
    opsAs($owner)->post(route('vendor.orders.cancel', $order->id), ['reason' => 'Damaged copy'])->assertSessionHasNoErrors();

    $lines = StockMovement::query()->where('product_id', $book->id)->orderBy('id')->get();
    expect($lines->map(fn ($m) => [$m->kind, $m->quantity, $m->stock_after])->all())->toBe([['in', 12, 12], ['adjustment', -2, 10], ['sale', -3, 7], ['cancel', 3, 10]])
        ->and($lines[2]->order_id)->toBe($order->id)->and($lines[1]->user_id)->toBe($owner->id)->and($lines[3]->user_id)->toBe($owner->id);

    // A product the shop does not count leaves no line.
    opsAs($owner)->post(route('vendor.products.store'), ['title' => 'Made To Order', 'price' => '50', 'tax_class' => 'standard', 'status' => 'active', 'visibility' => 'shop', 'stock' => 0, 'track_stock' => 0, 'lead_days' => 5]);
    expect(StockMovement::query()->count())->toBe(4);

    expect(fn () => $lines[0]->update(['quantity' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $lines[0]->delete())->toThrow(LogicException::class);
});

it('tells the shop once when stock falls to its low level, and again only after it was back above it', function () {
    Mail::fake();
    [$fitrah, $owner, $staff] = opsShop();
    $book = opsProduct($fitrah, 'Prayer Mat', 180, 5, ['low_stock_at' => 3]);
    $told = fn () => UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_low_stock_title'))->count();

    opsBuy(opsCustomer(), $book, 2);
    expect($told())->toBe(1)->and($book->refresh()->low_stock_notified_at)->not->toBeNull();
    expect(UserNotification::query()->where('user_id', $staff->id)->where('title', __('shop.notice_low_stock_title'))->count())->toBe(1);
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo('fitrah-owner@example.test') && $m->heading === __('shop.notice_low_stock_title'));

    opsBuy(opsCustomer(), $book->refresh(), 1);
    expect($told())->toBe(1);

    opsAs($owner)->post(route('vendor.stock.adjust'), ['product_id' => $book->id, 'mode' => 'in', 'quantity' => 10])->assertSessionHasNoErrors();
    expect($book->refresh()->low_stock_notified_at)->toBeNull();
    // 2 + 10 = 12 on the shelf; all twelve sell.
    opsBuy(opsCustomer(), $book->refresh(), 12);
    expect($told())->toBe(2);
    expect(UserNotification::query()->where('user_id', $owner->id)->where('message', __('shop.notice_sold_out_body', ['title' => 'Prayer Mat']))->exists())->toBeTrue();
});

it('receives, counts and corrects stock by hand on the stock page, with CSVs of the log and of what is low', function () {
    [$fitrah, $owner, $staff] = opsShop();
    [, $otherOwner] = opsShop('noor');
    $book = opsProduct($fitrah, 'Workbook', 60, 4, ['low_stock_at' => 5]);
    $shirt = opsProduct($fitrah, 'Kids T-shirt', 120, 0);
    $small = ProductVariant::query()->create(['product_id' => $shirt->id, 'name' => 'Small', 'sku' => 'TS-S', 'stock' => 2, 'is_active' => true]);
    ProductVariant::query()->create(['product_id' => $shirt->id, 'name' => 'Medium', 'sku' => 'TS-M', 'stock' => 0, 'is_active' => true]);

    opsAs($staff)->get(route('vendor.stock.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/VendorStock')->where('low_stock.0.variant', 'Medium')->where('low_stock.1.title', 'Workbook')->has('low_stock', 2));

    opsAs($staff)->post(route('vendor.stock.adjust'), ['product_id' => $book->id, 'mode' => 'in', 'quantity' => 20, 'note' => 'Delivery from printer'])->assertSessionHasNoErrors();
    opsAs($staff)->post(route('vendor.stock.adjust'), ['product_id' => $book->id, 'mode' => 'count', 'quantity' => 21])->assertSessionHasNoErrors();
    opsAs($staff)->post(route('vendor.stock.adjust'), ['product_id' => $book->id, 'mode' => 'adjustment', 'quantity' => -30])->assertSessionHasErrors('quantity');
    opsAs($staff)->post(route('vendor.stock.adjust'), ['product_id' => $shirt->id, 'mode' => 'in', 'quantity' => 3])->assertSessionHasErrors('variant_id');
    opsAs($staff)->post(route('vendor.stock.adjust'), ['product_id' => $shirt->id, 'variant_id' => $small->id, 'mode' => 'adjustment', 'quantity' => -1, 'note' => 'Stained'])->assertSessionHasNoErrors();
    opsAs($otherOwner)->post(route('vendor.stock.adjust'), ['product_id' => $book->id, 'mode' => 'in', 'quantity' => 5])->assertNotFound();

    expect($book->refresh()->stock)->toBe(21)->and($small->refresh()->stock)->toBe(1);
    expect(StockMovement::query()->where('product_id', $book->id)->orderBy('id')->pluck('kind')->all())->toBe(['in', 'adjustment']);

    $log = opsAs($owner)->get(route('vendor.stock.movements.export', ['kind' => 'adjustment']))->assertOk()->streamedContent();
    expect($log)->toContain('Stained')->toContain('Counted: 21')->not->toContain('Delivery from printer');
    $low = opsAs($owner)->get(route('vendor.stock.low.export'))->assertOk()->streamedContent();
    expect($low)->toContain('Kids T-shirt')->not->toContain('Workbook');
    expect(opsAs($otherOwner)->get(route('vendor.stock.movements.export'))->streamedContent())->not->toContain('Workbook');
});

it('exports the product sheet, checks an edited sheet without writing, then applies the good rows only', function () {
    [$fitrah, $owner] = opsShop();
    [$noor] = opsShop('noor');
    ProductCategory::query()->create(['name' => 'Workbooks', 'slug' => 'workbooks']);
    $book = opsProduct($fitrah, 'Tracing Book', 85, 10, ['sku' => 'FIT-TB', 'summary' => 'Trace the letters', 'details' => ['author' => 'Fitrah']]);
    $shirt = opsProduct($fitrah, 'Kids T-shirt', 120, 0, ['sku' => 'FIT-TS']);
    $small = ProductVariant::query()->create(['product_id' => $shirt->id, 'name' => 'Small', 'sku' => 'FIT-TS-S', 'stock' => 2, 'is_active' => true]);
    $theirs = opsProduct($noor, 'Their Book', 50, 5, ['sku' => 'NOOR-1']);

    $sheet = opsAs($owner)->get(route('vendor.products.export'))->assertOk()->streamedContent();
    expect(str_getcsv(strtok($sheet, "\n")))->toBe(\App\Domains\Bookshop\Support\ProductSheet::COLUMNS)
        ->and($sheet)->toContain('FIT-TB')->toContain('FIT-TS-S')->not->toContain('NOOR-1');
    expect(opsAs($owner)->get(route('vendor.stock.template'))->streamedContent())->toStartWith('id,sku,variant');

    $csv = "sku,variant,parent_sku,title,price,stock,category,author,status\n"
        ."FIT-TB,,,Tracing Book,90,25,workbooks,,\n"          // update price, stock, category; author cleared
        ."FIT-TS-S,Small,FIT-TS,,,7,,,\n"                      // a variant's stock
        ."FIT-NEW,,,Alphabet Cards,45,30,workbooks,Aisha,active\n" // a new product
        ."FIT-BAD,,,Bad Price,abc,1,,,\n"                     // not a number
        ."FIT-CAT,,,Wrong Category,10,1,toys-unknown,,\n"    // no such category
        ."FIT-NEW,,,Again,10,1,,,\n"                          // duplicate SKU in the file
        ."FIT-NOPRICE,,,No Price,,1,,,\n";                    // new without a price
    [$token, $preview] = opsImport($owner, $csv);
    expect($preview['summary'])->toMatchArray(['total' => 7, 'create' => 1, 'update' => 1, 'variant' => 1, 'errors' => 4]);
    $byLine = collect($preview['rows'])->keyBy('line');
    expect($byLine[2]['action'])->toBe('update')->and($byLine[2]['changes'])->toContain('price', 'stock', 'category', 'details')
        ->and($byLine[5]['errors'][0])->toContain('price')->and($byLine[6]['errors'][0])->toContain('toys-unknown');
    // Checked, not written.
    expect((string) $book->refresh()->price)->toBe('85.00')->and(Product::query()->where('sku', 'FIT-NEW')->exists())->toBeFalse();

    opsAs($owner)->post(route('vendor.stock.import.apply', $token))->assertRedirect(route('vendor.stock.index'))->assertSessionHas('success');
    $book->refresh();
    expect((string) $book->price)->toBe('90.00')->and($book->stock)->toBe(25)->and($book->summary)->toBe('Trace the letters')
        ->and($book->details)->toBe([])->and($book->category?->slug)->toBe('workbooks');
    expect($small->refresh()->stock)->toBe(7);
    $new = Product::query()->where('sku', 'FIT-NEW')->sole();
    expect($new->vendor_id)->toBe($fitrah->id)->and($new->status->value)->toBe('active')->and($new->details)->toBe(['author' => 'Aisha'])->and($new->stock)->toBe(30);
    expect(Product::query()->whereIn('sku', ['FIT-BAD', 'FIT-CAT', 'FIT-NOPRICE'])->count())->toBe(0);
    expect(StockMovement::query()->where('kind', 'import')->pluck('quantity')->sort()->values()->all())->toBe([5, 15]);
    expect(StockMovement::query()->where('product_id', $new->id)->value('kind'))->toBe('in');

    // Used once: applying again is refused.
    opsAs($owner)->post(route('vendor.stock.import.apply', $token))->assertSessionHasErrors('file');
    // Another shop's product is out of reach by id.
    [, $preview] = opsImport($owner, "id,price\n{$theirs->id},1\n");
    expect($preview['summary']['errors'])->toBe(1)->and((string) $theirs->refresh()->price)->toBe('50.00');
    // A file without the columns to match on is refused outright.
    opsAs($owner)->post(route('vendor.stock.import'), ['file' => opsCsv("colour,size\nred,1\n")])->assertSessionHasErrors('file');
});

it('handles a shop with 500 items: a 500-row import, fifty to a page, filters and bulk status', function () {
    [$fitrah, $owner] = opsShop();
    [$noor] = opsShop('noor');
    $theirs = opsProduct($noor, 'Their Book', 50, 5);
    $rows = "sku,title,price,stock,low_stock_at,status\n";
    for ($i = 1; $i <= 500; $i++) {
        $rows .= sprintf("BULK-%03d,Item %03d,%d,%d,3,active\n", $i, $i, 10 + $i, $i % 50);
    }
    [$token, $preview] = opsImport($owner, $rows);
    expect($preview['summary']['create'])->toBe(500);
    opsAs($owner)->post(route('vendor.stock.import.apply', $token))->assertSessionHas('success');
    expect(Product::query()->where('vendor_id', $fitrah->id)->count())->toBe(500);

    opsAs($owner)->get(route('vendor.index', ['page' => 2]))->assertOk()->assertInertia(fn ($page) => $page
        ->has('products', 50)->where('products_page.total', 500)->where('products_page.page', 2)->where('products_page.last_page', 10));
    opsAs($owner)->get(route('vendor.index', ['low' => 1]))->assertOk()->assertInertia(fn ($page) => $page->where('products_page.total', 40));
    opsAs($owner)->get(route('vendor.index', ['q' => 'BULK-499']))->assertOk()->assertInertia(fn ($page) => $page->where('products_page.total', 1));

    $ids = Product::query()->where('vendor_id', $fitrah->id)->orderBy('id')->limit(3)->pluck('id')->all();
    opsAs($owner)->post(route('vendor.products.bulk'), ['ids' => [...$ids, $theirs->id], 'status' => 'archived'])->assertSessionHas('success');
    expect(Product::query()->whereIn('id', $ids)->where('status', 'archived')->count())->toBe(3)->and($theirs->refresh()->status->value)->toBe('active');
});

it('duplicates a product as a draft with its variants and no SKU, stock or photos', function () {
    [$fitrah, $owner] = opsShop();
    [, $otherOwner] = opsShop('noor');
    $shirt = opsProduct($fitrah, 'Kids T-shirt', 120, 0, ['sku' => 'TS', 'summary' => 'Cotton']);
    ProductVariant::query()->create(['product_id' => $shirt->id, 'name' => 'Small', 'sku' => 'TS-S', 'stock' => 4, 'is_active' => true]);

    opsAs($owner)->post(route('vendor.products.duplicate', $shirt->id))->assertSessionHas('success');
    $copy = Product::query()->where('title', 'Copy of Kids T-shirt')->sole();
    expect($copy->status->value)->toBe('draft')->and($copy->sku)->toBeNull()->and($copy->stock)->toBe(0)->and($copy->summary)->toBe('Cotton')
        ->and($copy->slug)->toBe('kids-t-shirt-copy')->and($copy->variants()->pluck('name')->all())->toBe(['Small'])
        ->and($copy->variants()->value('sku'))->toBeNull()->and($copy->variants()->value('stock'))->toBe(0);
    opsAs($otherOwner)->post(route('vendor.products.duplicate', $shirt->id))->assertNotFound();
});

it('exports orders by date and line by line, for the shop and for the office', function () {
    [$fitrah, $owner] = opsShop();
    [$noor] = opsShop('noor');
    $book = opsProduct($fitrah, 'Tracing Book', 85, 50, ['sku' => 'FIT-TB']);
    $theirs = opsProduct($noor, 'Their Book', 50, 5, ['sku' => 'NOOR-1']);
    $old = opsBuy(opsCustomer(), $book, 2);
    $old->forceFill(['created_at' => now()->subDays(40)])->save();
    opsBuy(opsCustomer(), $book, 1);
    opsBuy(opsCustomer(), $theirs, 1);

    $recent = opsAs($owner)->get(route('vendor.orders.export', ['from' => now()->subDays(7)->toDateString()]))->assertOk()->streamedContent();
    expect(substr_count(trim($recent), "\n"))->toBe(1);
    $lines = opsAs($owner)->get(route('vendor.orders.lines.export'))->assertOk()->streamedContent();
    expect($lines)->toContain('FIT-TB')->not->toContain('NOOR-1')->and(substr_count(trim($lines), "\n"))->toBe(2);

    $office = opsOffice();
    $all = opsAs($office)->get(route('admin.bookshop.orders.lines.export'))->assertOk()->streamedContent();
    expect($all)->toContain('FIT-TB')->toContain('NOOR-1');
    expect(opsAs($office)->get(route('admin.bookshop.orders.lines.export', ['vendor' => $noor->id]))->streamedContent())->not->toContain('FIT-TB');
    expect(substr_count(trim(opsAs($office)->get(route('admin.bookshop.orders.export', ['to' => now()->subDays(30)->toDateString()]))->streamedContent()), "\n"))->toBe(1);
    expect(opsAs($office)->get(route('admin.bookshop.low-stock.export'))->assertOk()->streamedContent())->toStartWith('vendor,product');
    opsAs($owner)->get(route('admin.bookshop.orders.lines.export'))->assertForbidden();
});

it('sends notices by email and SMS where the office and the shop allow, and never to someone who switched shop notices off', function () {
    Mail::fake();
    $sms = new class implements SmsSenderInterface
    {
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = [$phoneNumber, $message];

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return ['success' => true, 'driver' => 'log'];
        }
    };
    app()->instance(SmsSenderInterface::class, $sms);
    [$fitrah, $owner, $staff] = opsShop();
    $book = opsProduct($fitrah, 'Tracing Book', 85, 50);

    // Defaults: customers and shops by email, nobody by SMS.
    $customer = opsCustomer();
    opsBuy($customer, $book, 1);
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo($customer->email) && $m->heading === __('shop.notice_paid_title'));
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo('fitrah-staff@example.test') && $m->heading === __('shop.notice_vendor_order_title'));
    expect($sms->sent)->toBe([]);

    // Staff cannot change the shop's choices; the owner can.
    opsAs($staff)->post(route('vendor.notices.save'), ['events' => ['new_order' => ['email' => 0, 'sms' => 1]]])->assertForbidden();
    opsAs($owner)->post(route('vendor.notices.save'), ['events' => ['new_order' => ['email' => 0, 'sms' => 1]]])->assertSessionHasNoErrors();
    // The office turns on customer SMS and shop SMS, turns off customer email.
    $office = opsOffice();
    opsAs($office)->post(route('admin.bookshop.notices.save'), ['customer_email' => 0, 'customer_sms' => 1, 'vendor_email' => 1, 'vendor_sms' => 1])->assertSessionHasNoErrors();
    opsAs($owner)->post(route('admin.bookshop.notices.save'), ['customer_email' => 1])->assertForbidden();

    Mail::fake();
    $second = opsCustomer();
    opsBuy($second, $book->refresh(), 1);
    Mail::assertNotQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo($second->email));
    Mail::assertNotQueued(BookshopNoticeMail::class, fn ($m) => $m->heading === __('shop.notice_vendor_order_title'));
    expect(collect($sms->sent)->pluck(0)->all())->toBe(['7712345', '7000001'])
        ->and($sms->sent[0][1])->toStartWith('Akuru Bookstore: '.__('shop.notice_paid_title'));

    // Someone who switched shop notices off gets none of them, in any channel.
    $quiet = opsCustomer();
    app(SaveNotificationPreferencesAction::class)->execute($quiet->id, ['shop' => false]);
    $sms->sent = [];
    opsBuy($quiet, $book->refresh(), 1);
    expect(collect($sms->sent)->pluck(0)->all())->toBe(['7000001']);
    expect(UserNotification::query()->where('user_id', $quiet->id)->count())->toBe(0);

    opsAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('notice_settings.events.new_order.sms', true)->where('notice_settings.office.customer_email', false));
});
