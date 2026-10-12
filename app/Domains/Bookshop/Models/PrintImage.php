<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** NAME_TAGS_PLAN NT2: Akuru's artwork for templates — public media the designer places. */
class PrintImage extends Model
{
    protected $fillable = ['media_file_id', 'alt', 'sort_order', 'uploaded_by'];
}
