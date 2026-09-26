<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** One picture in a shop's image library (B5): public media the storefront's sections pick from. */
class VendorStorefrontImage extends Model
{
    protected $fillable = ['vendor_id', 'media_file_id', 'alt', 'sort_order'];
}
