<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\BmlPaymentProvider;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryCategory;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

/**
 * The Digital Library's public pages, the wallet and gift cards in Dhivehi
 * and Arabic (BACKLOG C20, slice LT6, STATUS §5pk).
 *
 * The pages already went through the `public` book, but some 70 of its
 * phrases had no Dhivehi or Arabic — the shelf's search, its filters' "all"
 * options, My Library, the reader's buttons, the wallet's columns — and a kind
 * of item (Book, Article, Research paper) was English everywhere. The pages
 * also printed codes: a purchase's `paid`, a wallet row's `credit` and
 * `gift card`. An item's citations were headed by the raw key `public.Citations`.
 * Every refusal the reader could meet was English: the checkout's, the
 * wallet's, a gift card's, the payment start's — and the checkout's refusals
 * on `item` were never shown at all.
 */
uses(RefreshDatabase::class);

function libraryPageViews(): array
{
    return [
        'public/library/index.blade.php',
        'public/library/show.blade.php',
        'public/library/author.blade.php',
        'public/library/promotions.blade.php',
        'public/library/my.blade.php',
        'public/library/reader.blade.php',
        'public/library/payment-return.blade.php',
        'public/commerce/gift-cards.blade.php',
        'public/commerce/gift-card-return.blade.php',
        'public/commerce/wallet.blade.php',
    ];
}

/** The controllers that answer these pages (the `public` book). */
function libraryPageControllers(): array
{
    return [
        'app/Domains/Library/Http/Controllers/PublicLibraryController.php',
        'app/Domains/Library/Http/Controllers/LibraryReaderController.php',
        'app/Domains/Library/Http/Controllers/LibraryCheckoutController.php',
        'app/Domains/Commerce/Http/Controllers/WalletController.php',
        'app/Domains/Commerce/Http/Controllers/GiftCardPurchaseController.php',
    ];
}

/** The shared actions whose refusals these pages show (the `common` book). */
function libraryRefusingActions(): array
{
    return [
        'app/Domains/Library/Actions/StartLibraryCheckoutAction.php',
        'app/Domains/Commerce/Actions/DebitWalletAction.php',
        'app/Domains/Commerce/Actions/CreditWalletAction.php',
        'app/Domains/Commerce/Actions/RedeemGiftCardAction.php',
        'app/Domains/Commerce/Actions/StartGiftCardPurchaseAction.php',
        'app/Domains/Finance/Actions/InitiatePayablePaymentAction.php',
        'app/Domains/Finance/Services/Payment/BmlPaymentProvider.php',
    ];
}

function lt6Book(string $book, string $locale): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

function lt6PaidItem(string $title = 'LT6 Paid Book', float $price = 50): LibraryItem
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => $title,
        'content_type' => 'book',
        'access_type' => 'paid',
        'body' => '<p>LT6 page one.</p><!-- pagebreak --><p>LT6 page two.</p>',
    ]);
    $item->price = $price;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

function lt6FreeItem(string $access = 'free_login', array $extra = []): LibraryItem
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'LT6 Free Book '.uniqueFixtureSuffix(),
        'content_type' => 'book',
        'access_type' => $access,
        'body' => '<p>LT6 free page one.</p><!-- pagebreak --><p>LT6 free page two.</p>',
    ] + $extra);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('prints no English of its own on these pages', function () {
    $found = [];
    foreach (libraryPageViews() as $view) {
        $path = resource_path('views/'.$view);
        // A gift card code's shape; the writers' own sites by their own names.
        $allowed = ['AKG-XXXX-XXXX-XXXX', 'Facebook', 'Instagram', 'X', 'YouTube', 'LinkedIn', 'Telegram', 'MVR'];
        foreach ([...bladeBareEnglish($path, $allowed), ...bladeEnglishLiterals($path, $allowed)] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('says every phrase of these pages in Dhivehi and Arabic, each kind of item, state and source too', function () {
    $keys = ['public' => [], 'common' => []];
    foreach ([...array_map(fn ($view) => 'resources/views/'.$view, libraryPageViews()), ...libraryPageControllers(), ...libraryRefusingActions()] as $file) {
        foreach (['public', 'common'] as $book) {
            preg_match_all("/(?:__|trans_choice)\\(\\s*'{$book}\\.((?:[^'\\\\]|\\\\.)+)'/", file_get_contents(base_path($file)), $matches);
            $keys[$book] = [...$keys[$book], ...array_map('stripslashes', $matches[1])];
        }
    }
    // The ones the pages build from a code.
    foreach (['book', 'article', 'research', 'course_material'] as $type) {
        $keys['public'][] = $type;
    }
    foreach (ListLibraryItemsAction::SORTS as $sort) {
        $keys['public'][] = 'sort_'.$sort;
    }
    foreach (ListLibraryItemsAction::DIFFICULTIES as $level) {
        $keys['public'][] = 'difficulty_'.$level;
    }
    foreach (array_keys(ListLibraryItemsAction::READING_BANDS) as $band) {
        $keys['public'][] = 'reading_'.$band;
    }
    foreach (['pending', 'paid', 'refunded'] as $status) {
        $keys['public'][] = 'purchase_status_'.$status;
        $keys['public'][] = $status === 'refunded' ? 'failed' : $status; // a bought gift card's
    }
    foreach (['credit', 'debit'] as $type) {
        $keys['public'][] = 'wallet_type_'.$type;
    }
    foreach (['purchase', 'gift_card', 'admin', 'refund', 'promotion', 'reversal', 'bookshop_checkout', 'bookshop_refund', 'loyalty_reward', 'referral'] as $source) {
        $keys['public'][] = 'wallet_source_'.$source;
    }

    $gaps = [];
    foreach ($keys as $book => $bookKeys) {
        $en = lt6Book($book, 'en');
        foreach (array_unique($bookKeys) as $key) {
            if (str_ends_with($key, '_')) {
                continue;
            }
            foreach (['dv', 'ar'] as $locale) {
                $translated = lt6Book($book, $locale);
                if (! array_key_exists($key, $en) || ! array_key_exists($key, $translated) || $translated[$key] === $en[$key]) {
                    $gaps[] = "{$book}.{$locale} {$key}";
                }
            }
        }
    }

    expect($gaps)->toBe([]);
});

it('leaves no English refusal in the checkout, the wallet, gift cards or the payment start', function () {
    $found = [];
    foreach ([...libraryPageControllers(), ...libraryRefusingActions()] as $file) {
        foreach (refusalEnglishIn($file) as $line) {
            // The `public` book is keyed by its English, so a key reads as a sentence.
            if (! preg_match('/:\d+ public\./', $line)) {
                $found[] = $line;
            }
        }
    }

    expect($found)->toBe([]);
});

it('serves the shelf, an item, an author and the offers in Dhivehi and Arabic', function (string $locale) {
    $paid = lt6PaidItem();
    lt6FreeItem();
    $book = lt6Book('public', $locale);
    $user = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => 'LT6 Writer', 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);
    $writer = WriterProfile::query()->where('user_id', $user->id)->firstOrFail();
    app()->setLocale($locale);

    $shelf = $this->withoutLocalizationMiddleware()->get(route('public.library.index'))->assertOk()->getContent();
    $item = $this->withoutLocalizationMiddleware()->get(route('public.library.show', $paid->slug))->assertOk()->getContent();
    $author = $this->withoutLocalizationMiddleware()->get(route('public.library.author', $writer->slug))->assertOk()->getContent();
    $offers = $this->withoutLocalizationMiddleware()->get(route('public.library.promotions'))->assertOk()->getContent();
    $gifts = $this->withoutLocalizationMiddleware()->get(route('public.gift-cards.index'))->assertOk()->getContent();

    expect($shelf)->toContain(e($book['Search the library']))->toContain(e($book['book']))->toContain(e($book['All types']))
        ->not->toContain('placeholder="Search the library"')->not->toContain('>All categories<')
        ->and($item)->toContain(e($book['book']))->toContain(e(lt6Book('account', $locale)['guest_title']))->not->toContain('>Book<')
        ->and($author)->toContain(e($book['Published works']))->not->toContain('Nothing published yet.')
        ->and($offers)->toContain(e($book['No offers are running right now.']))
        ->and($gifts)->toContain(e($book['Gift cards are paid by card only. Discount codes and wallet money cannot buy a gift card.']));
})->with(['dv', 'ar']);

it('heads an item\'s citations with a word, not a key', function () {
    $item = lt6FreeItem('free_public', ['citations' => 'Ibn Hisham, Sira.']);

    $html = $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->assertOk()->getContent();

    expect($html)->toContain('>Citations<')->not->toContain('public.Citations');
});

it('reads to a signed-in reader in Dhivehi — the reader, My Library and the wallet, its codes named', function () {
    $reader = User::factory()->create();
    $free = lt6FreeItem();
    $paid = lt6PaidItem('LT6 Bought Book');
    LibraryPurchase::query()->create(['user_id' => $reader->id, 'library_item_id' => $paid->id, 'amount' => 50, 'currency' => 'MVR', 'status' => 'paid', 'purchased_at' => now()]);
    app(CreditWalletAction::class)->execute($reader->id, 25, 'gift_card', null, 'LT6 credit');
    $book = lt6Book('public', 'dv');
    app()->setLocale('dv');

    $page = $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.library.read', ['slug' => $free->slug, 'page' => 1]))->assertOk()->getContent();
    $forSale = $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.library.show', lt6PaidItem('LT6 For Sale', 75)->slug))->assertOk()->getContent();
    $mine = $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.library.my'))->assertOk()->getContent();
    $wallet = $this->actingAs($reader)->withoutLocalizationMiddleware()->get(route('public.wallet'))->assertOk()->getContent();

    expect($page)->toContain(e($book['Bookmark this page']))->toContain(e($book['Next']))->not->toContain('>Bookmark this page<')
        ->and($forSale)->toContain(e(__('public.Buy for :price', ['price' => 'MVR 75.00'])))->toContain(e($book['Pay with wallet']))->not->toContain('placeholder="Discount code"')
        ->and($mine)->toContain(e($book['Purchases']))->toContain(e($book['purchase_status_paid']))->not->toMatch('/purchase-status">paid/')
        ->and($wallet)->toContain(e($book['wallet_type_credit']))->toContain(e($book['wallet_source_gift_card']))
        ->not->toMatch('/wallet-type">credit/')->not->toMatch('/wallet-source">gift card/');
});

it('shows a category by the name the office gave for the page\'s language, and its English otherwise', function () {
    $category = LibraryCategory::query()->create(['name' => 'Tafsir', 'name_dv' => 'ތަފްސީރު', 'slug' => 'lt6-tafsir', 'is_active' => true]);
    $item = lt6FreeItem('free_public', ['library_category_id' => $category->id]);

    app()->setLocale('dv');
    $dvShelf = $this->withoutLocalizationMiddleware()->get(route('public.library.index'))->getContent();
    $dvItem = $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->getContent();
    app()->setLocale('ar');
    $arItem = $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->getContent();

    expect($dvShelf)->toContain('ތަފްސީރު (1)')
        ->and($dvItem)->toContain('>ތަފްސީރު<')
        ->and($arItem)->toContain('>Tafsir<');
});

it('refuses a gift card code in the page\'s language', function () {
    $this->actingAs(User::factory()->create())
        ->withHeader('Referer', url('/dv/my-wallet'))
        ->post(route('public.wallet.redeem'), ['code' => 'AKG-NOPE-NOPE-NOPE'])
        ->assertRedirect()
        ->assertSessionHasErrors(['code' => lt6Book('common', 'dv')['gift_card_error_not_found']]);
});

it('refuses a discount code on an item in the page\'s language, and writes nothing', function () {
    $item = lt6PaidItem();

    $this->actingAs(User::factory()->create())
        ->withHeader('Referer', url('/ar/library/'.$item->slug))
        ->post(route('public.library.checkout', $item->slug), ['discount_code' => 'LT6-NOPE'])
        ->assertRedirect()
        ->assertSessionHasErrors(['discount_code' => lt6Book('common', 'ar')['error_discount_not_found']]);

    expect(LibraryPurchase::query()->count())->toBe(0);
});

it('refuses to sell what is not for sale in the page\'s language, and says so on the item\'s page', function () {
    $item = lt6FreeItem('free_public');
    $refusal = lt6Book('common', 'dv')['library_error_not_for_sale'];

    $this->actingAs(User::factory()->create())
        ->withHeader('Referer', url('/dv/library/'.$item->slug))
        ->post(route('public.library.checkout', $item->slug))
        ->assertSessionHasErrors(['item' => $refusal]);

    $errors = (new ViewErrorBag)->put('default', new MessageBag(['item' => $refusal]));
    $this->withSession(['errors' => $errors])->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertSee('data-testid="item-refusal"', false)
        ->assertSee(e($refusal), false);
});

it('refuses a gift card with nowhere to send it in the page\'s language', function () {
    $this->actingAs(User::factory()->create())
        ->withHeader('Referer', url('/dv/gift-cards'))
        ->post(route('public.gift-cards.purchase'), ['amount' => 100, 'recipient_name' => 'Hawwa'])
        ->assertRedirect()
        ->assertSessionHasErrors(['recipient_email' => lt6Book('common', 'dv')['gift_card_error_send_to']]);
});

it('says a redeemed gift card in the page\'s language, the money as money', function () {
    $card = app(IssueGiftCardAction::class)->execute(['amount' => 25, 'recipient_name' => 'Hawwa', 'created_by' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create())
        ->withHeader('Referer', url('/ar/my-wallet'))
        ->post(route('public.wallet.redeem'), ['code' => $card['plain_code']])
        ->assertSessionHas('success', __('public.Gift card redeemed: :amount added to your wallet.', ['amount' => 'MVR 25.00'], 'ar'));
});

it('says a payment that could not start in the page\'s language, and in English as before', function () {
    config(['bml.api_key' => null]);
    $provider = new BmlPaymentProvider;

    app()->setLocale('dv');
    $dhivehi = $provider->initiate(new Payment(['amount' => 10]))->error;
    app()->setLocale('en');
    $english = $provider->initiate(new Payment(['amount' => 10]))->error;

    expect($dhivehi)->toBe(lt6Book('common', 'dv')['payment_error_not_configured'])
        ->and($english)->toBe('Payment gateway not configured');
});

it('says the wallet\'s refusals in English as before', function () {
    $user = User::factory()->create();

    expect(fn () => app(DebitWalletAction::class)->execute($user->id, 10, 'purchase', null, 'LT6'))
        ->toThrow(ValidationException::class, 'Insufficient wallet balance.');
});
