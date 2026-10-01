<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** COMMERCE_PARITY_PLAN P7b: an SMS offer the office sent, to whom, and what it cost. */
class ShopSmsCampaign extends Model
{
    public const AUDIENCES = ['opted_in', 'shop_buyers'];

    protected $fillable = ['audience', 'vendor_id', 'message', 'recipients', 'segments', 'rate', 'cost', 'status', 'sent_count', 'failed_count', 'created_by', 'finished_at'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'cost' => 'decimal:2', 'finished_at' => 'datetime'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function recipientRows(): HasMany
    {
        return $this->hasMany(ShopSmsCampaignRecipient::class);
    }
}
