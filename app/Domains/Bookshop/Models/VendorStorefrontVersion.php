<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only: what a storefront looked like when it was published, for roll-back. Never updated. */
class VendorStorefrontVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['vendor_storefront_id', 'number', 'identity', 'theme', 'sections', 'navigation', 'seo', 'pages', 'note', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'identity' => 'array',
            'theme' => 'array',
            'sections' => 'array',
            'navigation' => 'array',
            'seo' => 'array',
            'pages' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
