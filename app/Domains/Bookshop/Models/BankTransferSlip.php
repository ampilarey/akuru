<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\SlipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The customer's proof of a bank transfer (plan audit finding 1): private
 * media, readable by the paying customer and the office only. The office
 * confirms it and the checkout is paid; B3 lets the vendor confirm too.
 */
class BankTransferSlip extends Model
{
    protected $fillable = ['bookshop_checkout_id', 'media_file_id', 'reference', 'note', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return [
            'status' => SlipStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(BookshopCheckout::class, 'bookshop_checkout_id');
    }
}
