<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Standard extends Model
{
    protected $fillable = [
        'subject_id',
        'code',
        'title',
        'title_arabic',
        'title_dhivehi',
        'description',
        'parent_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(StandardTaggable::class);
    }
}
