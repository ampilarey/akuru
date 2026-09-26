<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** Shared across vendors (BOOKSHOP_PLAN §7): the office owns the list. */
class ProductCategory extends Model
{
    protected $fillable = ['parent_id', 'name', 'name_dv', 'name_ar', 'slug', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
