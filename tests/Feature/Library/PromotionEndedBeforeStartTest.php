<?php

use App\Domains\Commerce\Actions\ListPromotionCampaignsAction;
use App\Domains\Commerce\Models\PromotionCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * KNOWN_ISSUES K3: a campaign ended before it starts has its end moved to
 * now, before its start. The office's list and CSV say *ended before it
 * started* rather than read its window backwards; one ended while running
 * still reads *ended*.
 */
it('says a campaign ended before it started, on the list and in the CSV', function () {
    $office = actingSystemAdmin(['library.manage']);
    $web = $this->withoutLocalizationMiddleware()->actingAs($office);
    foreach (['Next year' => now()->addYear(), 'This week' => now()->subDay()] as $name => $starts) {
        $web->post(route('admin.library.promotions.store'), [
            'name' => $name, 'discount_type' => 'fixed', 'discount_value' => 5, 'funding_source' => 'akuru',
            'starts_at' => $starts->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $web->post(route('admin.library.promotions.end', PromotionCampaign::query()->where('name', $name)->value('id')))->assertSessionHas('success');
    }

    $states = collect(app(ListPromotionCampaignsAction::class)->execute())->pluck('state', 'name');
    expect($states['Next year'])->toBe('ended_before_start')->and($states['This week'])->toBe('ended');

    $web->get(route('admin.library.promotions'))->assertInertia(fn (Assert $page) => $page
        ->where('t.library_promotions_state_ended_before_start', 'Ended before it started'));
    expect($web->get(route('admin.library.promotions.export'))->streamedContent())->toContain(',ended_before_start,')->toContain(',ended,');

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(__('admin.library_promotions_state_ended_before_start', [], $locale))->not->toBe('admin.library_promotions_state_ended_before_start');
    }
});
