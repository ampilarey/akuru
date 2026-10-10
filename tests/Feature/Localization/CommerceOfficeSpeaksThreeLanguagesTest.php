<?php

use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Enums\DiscountType;
use App\Domains\Commerce\Enums\GiftCardStatus;
use App\Domains\Commerce\Models\GiftCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Commerce office in Dhivehi and Arabic (BACKLOG C21, slice CO1, STATUS
 * §5qv).
 *
 * The office's screen read no phrase book: every word on it was English, a
 * card's and a purchase's state, where a card came from, a discount code's
 * type and state and how a bought code went out were printed as codes, and
 * what the server said when it refused a card, a credit or a code was
 * English — a refused Deactivate was said nowhere. The names and reasons the
 * office types, and the ledger's own record of a credit, stay as written.
 */
uses(RefreshDatabase::class);

/** Where the server writes what the Commerce office says. */
function commerceOfficeServerFiles(): array
{
    return [
        'app/Domains/Commerce/Http/Controllers/AdminCommerceController.php',
        'app/Domains/Commerce/Actions/IssueGiftCardAction.php',
        'app/Domains/Commerce/Actions/DeactivateGiftCardAction.php',
        'app/Domains/Commerce/Actions/SaveDiscountCodeAction.php',
        'app/Domains/Commerce/Actions/CreditWalletAction.php',
    ];
}

function phraseBookForCo1(string $book, string $locale): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

it('keys every string on the Commerce office in three languages', function () {
    [$en, $dv, $ar] = [phraseBookForCo1('admin', 'en'), phraseBookForCo1('admin', 'dv'), phraseBookForCo1('admin', 'ar')];
    $screen = 'resources/js/Pages/Commerce/Admin.jsx';
    $source = file_get_contents(base_path($screen));

    preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
    expect($uses)->not->toBeEmpty();

    foreach ($uses as [, $key, $fallback]) {
        expect(array_key_exists($key, $en))->toBeTrue("admin.{$key} is missing in English")
            ->and(array_key_exists($key, $dv))->toBeTrue("admin.{$key} is missing in Dhivehi")
            ->and(array_key_exists($key, $ar))->toBeTrue("admin.{$key} is missing in Arabic")
            ->and($en[$key])->toBe(stripslashes($fallback), "admin.{$key} says something else in English than the screen")
            ->and($dv[$key])->not->toBe($en[$key], "admin.{$key} is English in Dhivehi")
            ->and($ar[$key])->not->toBe($en[$key], "admin.{$key} is English in Arabic");
    }

    expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, 'English text nodes: '.implode(' | ', $text[0] ?? []))
        ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, 'English attributes: '.implode(' | ', $attrs[0] ?? []))
        ->and(unnamedFields($source))->toBe([], 'fields with no name')
        ->and(routerVisitsWithoutRow($screen))->toBe([], 'posts with nowhere to say a refusal');
});

it('names a card\'s and a purchase\'s state, a card\'s source, a code\'s type and state and how a code went out, in all three languages', function () {
    $keys = [
        ...array_map(fn (GiftCardStatus $status) => 'admin.commerce_card_status_'.$status->value, GiftCardStatus::cases()),
        // A purchase is pending until the bank says paid or failed (gift_card_orders.status).
        ...array_map(fn (string $status) => 'admin.commerce_order_status_'.$status, ['pending', 'paid', 'failed']),
        // ListGiftCardsAction: bought through the shop, or issued by the office.
        ...array_map(fn (string $source) => 'admin.commerce_source_'.$source, ['purchased', 'office']),
        ...array_map(fn (DiscountType $type) => 'admin.commerce_type_'.$type->value, DiscountType::cases()),
        ...array_map(fn (string $status) => 'admin.commerce_discount_status_'.$status, ['active', 'inactive']),
        // IssueGiftCardOnPaymentConfirmed writes email, sms or email+sms.
        ...array_map(fn (string $via) => 'admin.commerce_via_'.$via, ['email', 'sms']),
        'admin.commerce_money',
    ];
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the Commerce office, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (commerceOfficeServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(commerceOfficeServerFiles());
    expect($keys)->toContain('common.gift_card_error_issue_amount', 'common.gift_card_error_nothing_left', 'common.error_discount_value', 'common.error_discount_code_exists', 'admin.commerce_flash_deactivated', 'admin.commerce_attr_user_id');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('serves the Commerce office in Dhivehi, and says what it saved and what it refused in Dhivehi', function () {
    $admin = actingSystemAdmin(['commerce.manage']);
    [$dv, $common] = [phraseBookForCo1('admin', 'dv'), phraseBookForCo1('common', 'dv')];
    app()->setLocale('dv');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.commerce.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Commerce/Admin')->where('t.commerce_owed_heading', $dv['commerce_owed_heading']));

    // A card taken out of circulation, and pressed again from a page opened before.
    $card = app(IssueGiftCardAction::class)->execute(['amount' => 25])['gift_card'];
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.gift-cards.deactivate', $card->id), ['reason' => 'The code leaked'])
        ->assertSessionHas('success', $dv['commerce_flash_deactivated']);
    expect(GiftCard::query()->find($card->id)->status)->toBe(GiftCardStatus::Deactivated);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.gift-cards.deactivate', $card->id), ['reason' => 'Again'])
        ->assertSessionHasErrors(['gift_card' => $common['gift_card_error_nothing_left']]);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.gift-cards.deactivate', $card->id + 1000), ['reason' => 'Again'])
        ->assertSessionHasErrors(['gift_card' => $common['gift_card_error_no_such_card']]);

    // A code saved, a percentage over a hundred, and the same code again.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.discount-codes.store'), ['code' => 'co1-ten', 'discount_type' => 'percentage', 'discount_value' => 10])
        ->assertSessionHas('success', $dv['commerce_flash_discount_saved']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.discount-codes.store'), ['code' => 'co1-more', 'discount_type' => 'percentage', 'discount_value' => 150])
        ->assertSessionHasErrors(['discount_value' => $common['error_discount_value']]);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.discount-codes.store'), ['code' => 'CO1-TEN', 'discount_type' => 'fixed', 'discount_value' => 5])
        ->assertSessionHasErrors(['code' => $common['error_discount_code_exists']]);

    // An account nobody has: Laravel's refusal, the field named in Dhivehi; no money moves.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.commerce.wallet-credits.store'), ['user_id' => 999999, 'amount' => 5])
        ->assertSessionHasErrors(['user_id' => trans('validation.exists', ['attribute' => $dv['commerce_attr_user_id']], 'dv')]);

    // The actions say the same wherever they are called from.
    expect(fn () => app(IssueGiftCardAction::class)->execute(['amount' => 0]))
        ->toThrow(ValidationException::class, $common['gift_card_error_issue_amount'])
        ->and(fn () => app(SaveDiscountCodeAction::class)->execute(['code' => 'CO1-X', 'discount_type' => 'free', 'discount_value' => 5]))
        ->toThrow(ValidationException::class, $common['error_discount_type']);
});
