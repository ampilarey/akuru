<?php

namespace App\Domains\Bookshop\Jobs;

use App\Domains\Bookshop\Actions\ShopSmsCampaignAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** COMMERCE_PARITY_PLAN P7b: sends a campaign's messages off the request. */
class SendShopSmsCampaignJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(ShopSmsCampaignAction $campaigns): void
    {
        $campaigns->deliver($this->campaignId);
    }
}
