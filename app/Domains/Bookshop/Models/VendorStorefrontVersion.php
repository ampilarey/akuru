<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only: what a storefront looked like when it was published, for roll-back. Never updated. */
class VendorStorefrontVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['vendor_storefront_id', 'number', 'identity', 'theme', 'note', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'identity' => 'array',
            'theme' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
