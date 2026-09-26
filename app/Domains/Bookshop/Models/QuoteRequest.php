<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A school's or group's request for a shop's price on a basket (BOOKSHOP_PLAN B9d). */
class QuoteRequest extends Model
{
    public const STATUSES = ['requested', 'quoted', 'accepted', 'declined', 'ordered', 'withdrawn'];

    protected $fillable = [
        'number', 'vendor_id', 'user_id', 'organisation', 'contact_phone', 'note', 'status', 'list_total', 'quoted_total', 'currency',
        'valid_until', 'vendor_note', 'quoted_by', 'quoted_at', 'accepted_at', 'ordered_at', 'declined_at',
    ];

    protected function casts(): array
    {
        return [
            'list_total' => 'decimal:2', 'quoted_total' => 'decimal:2', 'valid_until' => 'date',
            'quoted_at' => 'datetime', 'accepted_at' => 'datetime', 'ordered_at' => 'datetime', 'declined_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('id');
    }

    /** The quoted price still holds: quoted or accepted, and not past its date. */
    public function priceHolds(): bool
    {
        return in_array($this->status, ['quoted', 'accepted'], true) && $this->valid_until !== null && $this->valid_until->endOfDay()->isFuture();
    }
}
