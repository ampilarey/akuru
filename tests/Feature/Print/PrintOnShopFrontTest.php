<?php

use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * STATUS §5us: the Bookstore's front leads to printing — a chip in its link
 * row, a "Printed for you" section on its home with a card per product open
 * to customers, and an entry in the site's Bookstore menu — and shows none
 * of it while the print desk is off.
 */
uses(RefreshDatabase::class);

it('shows no way to printing while the print desk is off', function () {
    PrintDesk::ensure();
    config(['print.desk.enabled' => false]);

    printWeb()->get('/shop')->assertOk()
        ->assertDontSee('data-testid="shop-link-print"', false)
        ->assertDontSee('data-testid="shop-print"', false)
        ->assertDontSee(route('public.shop.print.index'), false);
    expect(PrintProducts::shopFront('en'))->toBe([]);
});

it('leads from the Bookstore to the print products open to customers', function () {
    PrintDesk::ensure();
    config(['print.desk.enabled' => true]);
    PrintProducts::product('cards')->update(['status' => 'active']);

    $page = printWeb()->get('/shop')->assertOk()
        ->assertSee('data-testid="shop-link-print"', false)
        ->assertSee('data-testid="shop-print"', false)
        // Name tags open whenever the desk is; cards because they are on sale; stickers and photos are not.
        ->assertSee('data-testid="shop-print-name_tags"', false)
        ->assertSee('data-testid="shop-print-cards"', false)
        ->assertDontSee('data-testid="shop-print-stickers"', false)
        ->assertSee(route('public.shop.print.kind', 'cards'), false)
        ->assertSee(__('shop.print_heading'));
    expect(array_column(PrintProducts::shopFront('en'), 'kind'))->toBe(['name_tags', 'cards']);
    // The site's Bookstore menu has it too.
    expect(substr_count($page->getContent(), route('public.shop.print.index')))->toBeGreaterThanOrEqual(3);
});
