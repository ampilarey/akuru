<?php

namespace App\Domains\Media\Models;

use App\Domains\Media\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'media_path',
        'document_type',
        'title',
        'expires_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'expires_at' => 'date',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
