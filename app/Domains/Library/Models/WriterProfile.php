<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WriterProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'slug',
        'bio',
        'qualifications',
        'expertise',
        'photo_media_file_id',
        'featured_item_ids',
        'social_links',
        'status',
        'approved_at',
        'approved_by',
        'default_commission',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'default_commission' => 'decimal:2',
            'featured_item_ids' => 'array',
            'social_links' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LibraryItem::class, 'writer_id');
    }
}
