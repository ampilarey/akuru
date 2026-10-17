<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** BLOG_PLAN BL2a (§4.3): a writer's series of posts, in order (STATUS §5sf). */
class LibraryPostSeries extends Model
{
    protected $table = 'library_post_series';

    protected $fillable = ['writer_id', 'title', 'slug', 'sort_order'];

    public function posts(): HasMany
    {
        return $this->hasMany(LibraryItem::class, 'series_id')->orderBy('series_position')->orderBy('id');
    }
}
