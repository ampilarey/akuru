<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\CheckoutPaymentMethod;
use App\Domains\Bookshop\Enums\CheckoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One basket paid for once (BOOKSHOP_PLAN §8): the Finance payable
 * (`bookshop_checkout`) under which one order per vendor sits. It waits
 * for money until `expires_at`, then the scheduler lets its stock go.
 */
class BookshopCheckout extends Model
{
    protected $fillable = [
        'number', 'user_id', 'status', 'payment_method', 'subtotal', 'discount', 'delivery_total', 'total',
        'currency', 'discount_code_id', 'payment_id', 'address_snapshot', 'notes', 'gift_message', 'expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CheckoutStatus::class,
            'payment_method' => CheckoutPaymentMethod::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_total' => 'decimal:2',
            'total' => 'decimal:2',
            'address_snapshot' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function slips(): HasMany
    {
        return $this->hasMany(BankTransferSlip::class)->orderByDesc('id');
    }
}
