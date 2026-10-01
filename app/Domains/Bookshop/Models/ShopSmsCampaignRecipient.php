<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** COMMERCE_PARITY_PLAN P7b: one phone a campaign went to, with the message as sent. */
class ShopSmsCampaignRecipient extends Model
{
    protected $fillable = ['shop_sms_campaign_id', 'shop_sms_optin_id', 'phone', 'body', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
