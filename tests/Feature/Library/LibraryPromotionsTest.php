<?php

use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Actions\SavePromotionCampaignAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\PromotionCampaign;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryCategoryAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B4 (LIBRARY_PLAN §18, STATUS §5ix): a promotion campaign is a scheduled
 * discount that applies by itself to what it covers. The shelf shows it,
 * the checkout charges it, the writer's earning knows who funded it, and
 * the office starts and ends it from a screen.
 */
function promoItem(string $title, float $price, array $extra = [])
{
    $item = app(SaveLibraryItemAction::class)->execute(array_merge([
        'title' => $title, 'content_type' => 'book', 'access_type' => 'paid', 'body' => '<p>'.$title.'</p>',
    ], $extra));
    $item->price = $price;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('shows the offer on the shelf and the item page, finds it with the discounted filter, and leaves the rest alone', function () {
    $fiqh = app(SaveLibraryCategoryAction::class)->execute(['name' => 'Fiqh']);
    $onOffer = promoItem('Fiqh of Fasting', 100, ['library_category_id' => $fiqh->id]);
    $notCovered = promoItem('Arabic Grammar', 80);
    $free = app(SaveLibraryItemAction::class)->execute(['title' => 'Free Primer', 'content_type' => 'article', 'access_type' => 'free_public', 'library_category_id' => $fiqh->id, 'body' => '<p>x</p>']);
    app(PublishLibraryItemAction::class)->execute($free->id, User::factory()->create()->id);

    $campaign = app(SavePromotionCampaignAction::class)->execute([
        'name' => 'Ramadan offer', 'discount_type' => 'percentage', 'discount_value' => 20, 'funding_source' => 'akuru',
        'ends_at' => now()->addDays(10)->toDateTimeString(),
        'targets' => [['type' => 'library_category', 'id' => $fiqh->id]],
    ]);

    $rows = collect(app(ListLibraryItemsAction::class)->execute())->keyBy('slug');
    expect($rows[$onOffer->slug]['promotion'])->toMatchArray(['name' => 'Ramadan offer', 'amount_off' => 20.0, 'price' => 80.0])
        ->and($rows[$notCovered->slug]['promotion'])->toBeNull()
        ->and($rows[$free->slug]['promotion'])->toBeNull();

    $discounted = collect(app(ListLibraryItemsAction::class)->execute(['discounted' => 1]))->pluck('slug')->all();
    expect($discounted)->toBe([$onOffer->slug])
        ->and(collect(app(ListLibraryItemsAction::class)->execute(['campaign' => $campaign->slug]))->pluck('slug')->all())->toBe([$onOffer->slug])
        ->and(app(ListLibraryItemsAction::class)->execute(['campaign' => 'no-such-offer']))->toBe([]);

    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()->assertSee('Current offers')->assertSee('Ramadan offer')->assertSee('data-promo="'.$onOffer->slug.'"', false);
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get(route('public.library.show', $onOffer->slug))
        ->assertOk()->assertSee('data-testid="item-promotion"', false)->assertSee('Buy for MVR 80.00');
    $this->withoutLocalizationMiddleware()->get(route('public.library.promotions'))
        ->assertOk()->assertSee('Ramadan offer')->assertSee('data-item="'.$onOffer->slug.'"', false)->assertDontSee('data-item="'.$notCovered->slug.'"', false);
});

it('charges the campaign price at checkout, records the redemption against the campaign, and funds the writer as the campaign says', function () {
    $writerUser = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('writer', 'web');
    $writer = WriterProfile::query()->create(['user_id' => $writerUser->id, 'display_name' => 'Ustadha Aminath', 'slug' => 'ustadha-aminath', 'status' => 'active', 'approved_at' => now()]);
    $book = promoItem('Sun letters', 200);
    $book->writer_id = $writer->id;
    $book->save();

    // Two campaigns cover the book; the reader gets the bigger saving, capped by its maximum.
    app(SavePromotionCampaignAction::class)->execute(['name' => 'Small', 'discount_type' => 'fixed', 'discount_value' => 10, 'targets' => [['type' => 'all']]]);
    $big = app(SavePromotionCampaignAction::class)->execute([
        'name' => 'Writer launch', 'discount_type' => 'percentage', 'discount_value' => 50, 'max_discount_amount' => 60, 'funding_source' => 'akuru',
        'targets' => [['type' => 'writer_profile', 'id' => $writer->id]],
    ]);

    $buyer = User::factory()->create();
    app(\App\Domains\Commerce\Actions\CreditWalletAction::class)->execute($buyer->id, 500, 'admin', null, 'Top-up');
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $book->slug), ['pay_with_wallet' => 1])
        ->assertRedirect(route('public.library.read', ['slug' => $book->slug]));

    $purchase = LibraryPurchase::query()->firstOrFail();
    $redemption = DiscountRedemption::query()->firstOrFail();
    expect((string) $purchase->amount)->toBe('140.00')
        ->and((int) $redemption->promotion_campaign_id)->toBe($big->id)
        ->and($redemption->discount_code_id)->toBeNull()
        ->and($redemption->status)->toBe('confirmed')
        ->and((string) $redemption->amount_discounted)->toBe('60.00');

    // Akuru funds it: the writer's 70% is of the original 200, not of the 140 paid.
    $earning = WriterEarning::query()->firstOrFail();
    expect((string) $earning->writer_amount)->toBe('140.00')
        ->and($earning->discount_funding_source)->toBe('akuru')
        ->and((string) $earning->discount_amount)->toBe('60.00');

    // A typed code is the reader's choice instead of the campaign: full price, minus the code, and no campaign redemption.
    app(SaveDiscountCodeAction::class)->execute(['code' => 'TEN', 'discount_type' => 'fixed', 'discount_value' => 10]);
    $second = User::factory()->create();
    app(\App\Domains\Commerce\Actions\CreditWalletAction::class)->execute($second->id, 500, 'admin', null, 'Top-up');
    $this->withoutLocalizationMiddleware()->actingAs($second)
        ->post(route('public.library.checkout', $book->slug), ['pay_with_wallet' => 1, 'discount_code' => 'TEN'])
        ->assertRedirect(route('public.library.read', ['slug' => $book->slug]));
    $codePurchase = LibraryPurchase::query()->where('user_id', $second->id)->firstOrFail();
    expect((string) $codePurchase->amount)->toBe('190.00')
        ->and(DiscountRedemption::query()->where('purchase_id', $codePurchase->id)->value('promotion_campaign_id'))->toBeNull();

    // The office sees the campaign's use.
    $listed = collect(app(\App\Domains\Commerce\Actions\ListPromotionCampaignsAction::class)->execute())->firstWhere('slug', $big->slug);
    expect($listed['uses'])->toBe(1)->and($listed['confirmed'])->toBe(1)->and($listed['given'])->toBe(60.0)->and($listed['state'])->toBe('live');
});

it('lets the office start, list, export and end a campaign, after which the offer is gone', function () {
    $office = actingSystemAdmin(['library.manage']);
    $book = promoItem('Moon letters', 50);

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), [
            'name' => 'Back to school', 'discount_type' => 'fixed', 'discount_value' => 5, 'funding_source' => 'shared',
            'targets' => [['type' => 'library_item', 'id' => $book->id]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Campaign started.');
    $campaign = PromotionCampaign::query()->firstOrFail();
    expect($campaign->targets()->count())->toBe(1)
        ->and(app(ListLibraryItemsAction::class)->execute(['discounted' => 1]))->toHaveCount(1);

    // Bad input is refused, not silently fixed.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), ['name' => 'Too much', 'discount_type' => 'percentage', 'discount_value' => 150, 'funding_source' => 'akuru'])
        ->assertSessionHasErrors('discount_value');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), ['name' => 'Backwards', 'discount_type' => 'fixed', 'discount_value' => 5, 'funding_source' => 'akuru', 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->toDateTimeString()])
        ->assertSessionHasErrors('ends_at');

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.library.promotions'))->assertOk();
    $csv = $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.library.promotions.export'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('name,slug,starts_at')->toContain('Back to school')->toContain('library_item:'.$book->id);

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.end', $campaign->id))->assertSessionHas('success', 'Campaign ended.');
    expect($campaign->refresh()->status)->toBe('ended')
        ->and(app(ListLibraryItemsAction::class)->execute(['discounted' => 1]))->toBe([])
        ->and(collect(app(ListLibraryItemsAction::class)->execute())->firstWhere('slug', $book->slug)['promotion'])->toBeNull();
    $this->withoutLocalizationMiddleware()->get(route('public.library.promotions'))->assertOk()->assertSee('No offers are running right now.');

    // A reader cannot reach the office screen.
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get(route('admin.library.promotions'))->assertForbidden();
});
