<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * COMMERCE_PARITY_PLAN P8c: one line of a credit account's ledger — a
 * charge, a payment or a refund. Append-only (rule 12): written by
 * `ShopCreditAction` and never changed or removed.
 */
class ShopCreditEntry extends Model
{
    public const KINDS = ['charge', 'payment', 'deposit', 'refund'];

    public $timestamps = false;

    protected $fillable = ['shop_credit_account_id', 'kind', 'amount', 'bookshop_checkout_id', 'order_refund_id', 'reference', 'note', 'created_by', 'created_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Credit entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Credit entries are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }
}
