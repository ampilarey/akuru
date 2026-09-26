<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One counter of a shop's funnel for a day (BOOKSHOP_PLAN B9e): a step
 * (`metric`) and what it was about (`subject`: `home`, `page:<slug>`,
 * `collection:<slug>`, `product:<id>`, or empty). Counts only.
 */
class ShopDailyStat extends Model
{
    /** The funnel, in order, then what was sold. */
    public const FUNNEL = ['shop_view', 'product_view', 'cart_add', 'checkout', 'order_paid'];

    public const METRICS = ['shop_view', 'product_view', 'cart_add', 'checkout', 'order_paid', 'product_sold'];

    public $timestamps = false;

    protected $fillable = ['vendor_id', 'day', 'metric', 'subject', 'count', 'amount'];

    protected function casts(): array
    {
        return ['day' => 'date', 'count' => 'integer', 'amount' => 'decimal:2'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
