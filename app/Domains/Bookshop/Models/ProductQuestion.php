<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's question about a product, and the shop's public answer
 * (STATUS §5le). Shown on the product page once answered; the office may
 * hide it with a note the shop sees.
 */
class ProductQuestion extends Model
{
    public const PUBLISHED = 'published';

    public const HIDDEN = 'hidden';

    protected $fillable = [
        'product_id', 'vendor_id', 'user_id', 'question', 'answer', 'answered_at', 'answered_by',
        'status', 'moderated_at', 'moderated_by', 'moderation_note',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
