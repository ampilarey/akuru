<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who asked a shop for its news (BOOKSHOP_PLAN §6.3 "Newsletter", slice B9c). */
class VendorNewsletterSubscriber extends Model
{
    protected $fillable = ['vendor_id', 'email', 'name', 'user_id', 'token', 'consented_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
