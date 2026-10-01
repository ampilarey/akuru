<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** COMMERCE_PARITY_PLAN P8c: a customer's credit account — its limit, terms and status. The money is in its entries. */
class ShopCreditAccount extends Model
{
    public const STATUSES = ['active', 'suspended'];

    protected $fillable = ['user_id', 'organisation', 'credit_limit', 'terms_days', 'status', 'note', 'opened_by'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'terms_days' => 'integer'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ShopCreditEntry::class);
    }
}
