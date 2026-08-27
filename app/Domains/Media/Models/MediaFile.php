<?php

namespace App\Domains\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFile extends Model
{
    protected $fillable = [
        'disk',
        'path',
        'mime',
        'original_name',
        'size',
        'uploaded_by',
        'visibility',
        'process_status',
        'processed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'processed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.user'), 'uploaded_by');
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }
}
