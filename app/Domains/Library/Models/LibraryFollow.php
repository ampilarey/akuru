<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** BLOG_PLAN BL4: a reader following a blog (STATUS §5sk). */
class LibraryFollow extends Model
{
    protected $fillable = ['user_id', 'writer_id', 'notify_email'];

    protected function casts(): array
    {
        return ['notify_email' => 'boolean'];
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(WriterProfile::class, 'writer_id');
    }
}
