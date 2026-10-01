<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** COMMERCE_PARITY_PLAN P6b: an order Akuru delivers, its driver, and the proof. */
class OrderDelivery extends Model
{
    protected $fillable = ['order_id', 'delivery_driver_id', 'assigned_by', 'assigned_at', 'picked_up_at', 'delivered_at', 'proof_media_file_id', 'note'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'picked_up_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
    }
}
