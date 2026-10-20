<?php

use App\Domains\Bookshop\Actions\Cart\PresentCartAction;
use App\Domains\Bookshop\Actions\Print\AddPrintJobToCartAction;
use App\Domains\Bookshop\Actions\Print\ProofPrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Models\QuoteItem;
use App\Domains\Bookshop\Models\ShopCreditAccount;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintLine;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP6b (STATUS §5to): a school's print job in a bulk
 * quote — asked from the basket like any line, priced by the office for
 * Akuru, accepted back into the basket at the quoted price, which holds as
 * long as the quote does. A customer with a credit account asks at any size.
 */
uses(RefreshDatabase::class);

function pp6bDesk(): void
{
    PrintDesk::ensure();
    PrintDesk::product()->update(['status' => 'active']);
    PrintDesk::vendor()->update(['status' => 'active']);
    PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail()->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    printHostCanPrint();
    Storage::fake('local');
}

/** An approved name-tag job of this many sheets at 3.00, in its owner's basket. */
function pp6bJobInCart(User $customer, int $sheets): PrintJob
{
    $save = app(SavePrintJobAction::class);
    $job = $save->start(PrintTemplate::query()->where('kind', 'name_tag')->orderBy('sort_order')->firstOrFail(), $customer->id, null, 'en');
    $job = $save->update($job, ['entry' => ['mode' => 'same', 'row' => ['name' => 'Aishath', 'class' => '3B']], 'sheets' => $sheets, 'colour' => true], 'en');
    $proof = app(ProofPrintJobAction::class);
    $job = $proof->approve($proof->proof($job, $customer->id, 'en'), 'en');
    app(AddPrintJobToCartAction::class)->execute(Cart::query()->firstOrCreate(['user_id' => $customer->id]), $job, $customer->id);

    return $job;
}

it('quotes a print job from the basket, priced by the office for Akuru, and charges the quoted price', function () {
    pp6bDesk();
    $school = User::factory()->create();
    $job = pp6bJobInCart($school, 12);

    $quote = app(CustomerQuotesAction::class)->request($school->id, PrintDesk::vendor()->slug, ['organisation' => 'Hiriya School']);
    $line = $quote->items()->sole();
    expect($line->print_job_id)->toBe($job->id)->and($line->quantity)->toBe(12)->and((float) $line->list_price)->toBe(3.0)
        ->and($line->title)->toContain((string) $job->name);

    // The office sees it on the print desk and prices it; a stranger may not.
    printWeb()->actingAs(User::factory()->create())->get(route('admin.bookshop.print.quotes'))->assertForbidden();
    $office = printOffice();
    printWeb()->actingAs($office)->get(route('admin.bookshop.print.quotes'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Bookshop/VendorQuotes')->where('office', true)->where('base', '/admin/bookshop/print/quotes')->where('quotes.0.items.0.print_job_id', $job->id));
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.quotes.quote', $quote->id), ['prices' => [$line->id => 2.50], 'valid_days' => 30])->assertSessionHasNoErrors();
    expect((float) $quote->fresh()->quoted_total)->toBe(30.0);

    // Accepted, the job is back in the basket at the quoted price.
    app(CustomerQuotesAction::class)->accept($school->id, $quote->number);
    $item = CartItem::query()->where('print_job_id', $job->id)->sole();
    expect($item->quote_item_id)->toBe($line->id)->and(PrintLine::quoted($item, $item->product))->toBe(2.5);
    $cart = app(PresentCartAction::class)->execute(Cart::query()->where('user_id', $school->id)->first());
    $shown = collect($cart['groups'])->flatMap(fn ($g) => $g['lines'])->firstWhere('id', $item->id);
    expect($shown['unit_price'])->toBe('2.50')->and($shown['line_total'])->toBe('30.00')->and($shown['quoted'])->toBeTrue()->and($cart['problems'])->toBe([]);

    // The approval's seven days pass; the quote still holds the line.
    $this->travel(10)->days();
    expect(PrintLine::blocker($job->fresh(), 12, $school->id))->toBe('print.error_cart_price_lapsed')
        ->and(PrintLine::blocker($job->fresh(), 12, $school->id, quoted: PrintLine::quoted($item->fresh(), $item->product) !== null))->toBeNull();
});

it('asks a school with a credit account for no minimum, and everyone else for the shop\'s', function () {
    pp6bDesk();
    $small = User::factory()->create();
    pp6bJobInCart($small, 2);
    expect(fn () => app(CustomerQuotesAction::class)->request($small->id, PrintDesk::vendor()->slug, ['organisation' => 'A family']))
        ->toThrow(ValidationException::class);

    $credit = User::factory()->create();
    ShopCreditAccount::query()->create(['user_id' => $credit->id, 'organisation' => 'Hiriya School', 'credit_limit' => 5000, 'terms_days' => 30, 'status' => 'active', 'opened_by' => printOffice()->id]);
    pp6bJobInCart($credit, 2);
    expect(CustomerQuotesAction::minimumFor($credit->id))->toBe(1)
        ->and(CustomerQuotesAction::minimumFor($small->id))->toBe((int) config('bookshop.quotes.min_quantity', 10));
    expect(app(CustomerQuotesAction::class)->request($credit->id, PrintDesk::vendor()->slug, ['organisation' => 'Hiriya School'])->items()->sole()->print_job_id)->not->toBeNull();
});

it('puts each quoted job back once, at its own line\'s price', function () {
    pp6bDesk();
    $school = User::factory()->create();
    $first = pp6bJobInCart($school, 6);
    $second = pp6bJobInCart($school, 6);
    $quote = app(CustomerQuotesAction::class)->request($school->id, PrintDesk::vendor()->slug, ['organisation' => 'Hiriya School']);
    expect($quote->items()->pluck('print_job_id')->sort()->values()->all())->toBe([$first->id, $second->id]);

    $lines = $quote->items()->get()->keyBy('print_job_id');
    printWeb()->actingAs(printOffice())->post(route('admin.bookshop.print.quotes.quote', $quote->id), ['prices' => [$lines[$first->id]->id => 2.00, $lines[$second->id]->id => 2.80], 'valid_days' => 14]);
    app(CustomerQuotesAction::class)->accept($school->id, $quote->number);

    // Each job is back once, at its own line's price.
    $items = CartItem::query()->whereNotNull('print_job_id')->get()->keyBy('print_job_id');
    expect($items)->toHaveCount(2)
        ->and(PrintLine::quoted($items[$first->id], $items[$first->id]->product))->toBe(2.0)
        ->and(PrintLine::quoted($items[$second->id], $items[$second->id]->product))->toBe(2.8);
    // A line pointed at another job's quote line pays nothing quoted.
    $items[$first->id]->update(['quote_item_id' => QuoteItem::query()->where('print_job_id', $second->id)->value('id')]);
    expect(PrintLine::quoted($items[$first->id]->fresh(), $items[$first->id]->product))->toBeNull();
});
