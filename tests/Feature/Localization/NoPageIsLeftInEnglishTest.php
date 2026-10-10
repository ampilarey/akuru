<?php

use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * No page is left in English (BACKLOG C21, slice SH1, STATUS §5qw).
 *
 * C21 found its slices by running one static rule over every page: a text
 * node that starts with an English word, or a placeholder, aria-label, title
 * or phone caption written in English. Each slice's own test held its pages
 * to the rule, and nothing held the rest. After CO1 the rule, run over every
 * page again, found three English words left on the Bookstore's pages, and
 * five fields a screen reader named by an example (*https://*) where the
 * label around them named them. This holds every page to the rule, so a page
 * written with English words fails here first.
 *
 * Some words read the same in every language: a currency's and a product
 * code's abbreviation, and SMS. An example address and a slug's example are
 * placeholders, written as they are typed. The developer's own test page
 * (`/inertia-test`) is on no menu.
 */
uses(RefreshDatabase::class);

/** Every page somebody opens; the developer's smoke page aside. */
function pagesSomebodyOpens(): array
{
    $pages = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js/Pages'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'jsx' && $file->getFilename() !== 'InertiaTest.jsx') {
            $pages[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }
    sort($pages);

    return $pages;
}

/** Words that read the same in every language. */
function wordsTheSameInEveryLanguage(): array
{
    return ['MVR', 'SKU', 'SMS'];
}

/** A placeholder written as it is typed: an address, or a slug's example. */
function placeholderAsTyped(string $value): bool
{
    return preg_match('#^(https?://|www\.)#', $value) === 1 || $value === 'ramadan';
}

it('leaves no English words on any page', function () {
    $found = [];
    foreach (pagesSomebodyOpens() as $page) {
        $source = stripJsComments(file_get_contents(base_path($page)));

        // A text node; not the `>` of an arrow function.
        preg_match_all('/(?<!=)>\s*([A-Z][A-Za-z]+[^<>{}]*)</', $source, $text);
        foreach ($text[1] as $words) {
            if (! in_array(trim($words), wordsTheSameInEveryLanguage(), true)) {
                $found[] = "{$page}: “".trim($words).'”';
            }
        }

        preg_match_all('/(placeholder|aria-label|title|data-label)="([A-Za-z][^"]*)"/', $source, $attributes, PREG_SET_ORDER);
        foreach ($attributes as [, $name, $value]) {
            if (in_array($value, wordsTheSameInEveryLanguage(), true) || ($name === 'placeholder' && placeholderAsTyped($value))) {
                continue;
            }
            $found[] = "{$page}: {$name}=“{$value}”";
        }
    }

    expect(pagesSomebodyOpens())->not->toBeEmpty()
        ->and($found)->toBe([], "English on a page, which a reader in Dhivehi or Arabic meets as it is:\n  ".implode("\n  ", $found));
});

it('names no field by an example of what to type in it', function () {
    $found = [];
    foreach (pagesSomebodyOpens() as $page) {
        preg_match_all('/aria-label="((?:https?:\/\/|www\.)[^"]*)"/', stripJsComments(file_get_contents(base_path($page))), $labels);
        foreach ($labels[1] as $label) {
            $found[] = "{$page}: aria-label=“{$label}”";
        }
    }

    expect($found)->toBe([], "A screen reader reads the example in place of the field's name:\n  ".implode("\n  ", $found));
});

it('names the Bookstore office\'s last words and the seller portal\'s gate in three languages', function () {
    foreach (['shop.application_id_front', 'shop.application_id_back', 'shop.error_not_a_member', 'shop.error_agreement_first', 'common.error_discount_status'] as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }

    // The tags box's examples are in the page's language, and the box splits on its comma.
    expect(trans('shop.customer_tags_hint', [], 'dv'))->not->toMatch('/[A-Za-z]/')
        ->and(trans('shop.customer_tags_hint', [], 'ar'))->not->toMatch('/[A-Za-z]/')
        ->and(file_get_contents(resource_path('js/Pages/Bookshop/Customer.jsx')))->toContain('split(/[,،]/)');

    $english = [...refusalEnglishIn('app/Domains/Bookshop/Http/Controllers/Concerns/AuthorizesVendor.php'), ...refusalEnglishIn('app/Domains/Commerce/Actions/ManageScopedDiscountCodesAction.php')];
    expect($english)->toBe([]);
});

it('refuses a seller portal page in the page\'s language', function () {
    Role::findOrCreate('vendor', 'web');
    $created = app(CreateVendorAction::class)->execute(['name' => 'SH1 Shop', 'owner_name' => 'SH1 Owner', 'owner_email' => 'sh1-owner@example.test'], User::factory()->create()->id);
    $owner = User::query()->findOrFail($created['owner_user_id']);
    $dv = require base_path('resources/lang/dv/shop.php');
    app()->setLocale('dv');

    // The agreement not yet accepted, and somebody who is no shop's member.
    $this->withoutLocalizationMiddleware()->actingAs($owner)
        ->get(route('vendor.orders.index'))->assertForbidden()->assertSee($dv['error_agreement_first']);
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('vendor.orders.index'))->assertForbidden()->assertSee($dv['error_not_a_member']);
});
