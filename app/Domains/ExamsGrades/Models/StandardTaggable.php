<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StandardTaggable extends Model
{
    protected $fillable = [
        'standard_id',
        'taggable_type',
        'taggable_id',
    ];

    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class);
    }

    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }
}
