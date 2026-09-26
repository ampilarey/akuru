<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One change to a counted product's or variant's stock (BOOKSHOP_PLAN §9,
 * slice B8). Append-only: the app writes rows through `Support\StockLedger`
 * and never changes or removes one, so the log is what happened.
 */
class StockMovement extends Model
{
    public const KINDS = ['in', 'sale', 'cancel', 'return', 'adjustment', 'import'];

    public $timestamps = false;

    protected $fillable = ['vendor_id', 'product_id', 'product_variant_id', 'kind', 'quantity', 'stock_after', 'order_id', 'note', 'user_id', 'created_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Stock movements are append-only.'));
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'quantity' => 'integer', 'stock_after' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
