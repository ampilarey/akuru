<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's review of a product they received (BOOKSHOP_PLAN §4
 * "Trust"; decision 12: on, moderated). One per delivered order line; the
 * vendor may reply; the office may hide it or, when pre-moderation is on,
 * publish it.
 */
class ProductReview extends Model
{
    public const PUBLISHED = 'published';

    public const PENDING = 'pending';

    public const HIDDEN = 'hidden';

    protected $fillable = [
        'product_id', 'vendor_id', 'order_id', 'order_item_id', 'user_id', 'rating', 'body', 'status',
        'vendor_reply', 'replied_at', 'replied_by', 'moderated_at', 'moderated_by', 'moderation_note',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
