<?php

namespace App\Domains\Pronunciation\Models;

use Illuminate\Database\Eloquent\Model;

/** §51.16 audit: append-only — rows are never updated or deleted. */
class AiModelVersionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ai_model_version_id',
        'action',
        'by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
