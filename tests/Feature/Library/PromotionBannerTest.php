<?php

use App\Domains\Commerce\Actions\ListPromotionCampaignsAction;
use App\Domains\Commerce\Models\PromotionCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * B4c (LIBRARY_PLAN §18 "banner", STATUS §5iz): a campaign may carry a
 * picture, public media, shown on the offers page and in the office's list.
 */
it('stores a campaign banner as public media and shows it on the offers page, refusing a file that is not an image', function () {
    Storage::fake('public');
    $office = actingSystemAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), [
            'name' => 'Ramadan offer', 'discount_type' => 'percentage', 'discount_value' => 20, 'funding_source' => 'akuru',
            'targets' => [['type' => 'all']],
            'banner' => UploadedFile::fake()->image('ramadan.jpg', 1200, 400),
        ])->assertSessionHasNoErrors();

    $campaign = PromotionCampaign::query()->firstOrFail();
    expect($campaign->banner_media_file_id)->not->toBeNull();
    $listed = collect(app(ListPromotionCampaignsAction::class)->execute())->firstWhere('slug', $campaign->slug);
    expect($listed['banner_url'])->toBeString()->toContain('promotion-banners');

    $this->withoutLocalizationMiddleware()->get(route('public.library.promotions'))
        ->assertOk()->assertSee('data-banner="'.$campaign->slug.'"', false)->assertSee($listed['banner_url'], false);

    // Not an image: refused, and no second campaign appears.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), [
            'name' => 'Bad banner', 'discount_type' => 'fixed', 'discount_value' => 5, 'funding_source' => 'akuru',
            'banner' => UploadedFile::fake()->create('banner.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('banner');
    expect(PromotionCampaign::query()->count())->toBe(1);
});
