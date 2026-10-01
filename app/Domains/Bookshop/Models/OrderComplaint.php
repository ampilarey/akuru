<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** COMMERCE_PARITY_PLAN P7a: a problem a customer reported with an order, and the office's answer. */
class OrderComplaint extends Model
{
    public const KINDS = ['damaged', 'missing', 'wrong_item', 'late', 'other'];

    public const STATUSES = ['open', 'in_progress', 'resolved'];

    protected $fillable = ['order_id', 'vendor_id', 'user_id', 'kind', 'body', 'photo_media_file_id', 'status', 'reply', 'replied_by', 'replied_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
