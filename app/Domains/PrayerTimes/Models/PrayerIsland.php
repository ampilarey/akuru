<?php

namespace App\Domains\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrayerIsland extends Model
{
    protected $fillable = [
        'id',
        'category_id',
        'atoll',
        'atoll_latin',
        'name',
        'name_latin',
        'offset_minutes',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PrayerCategory::class, 'category_id');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(PrayerBroadcast::class, 'island_id');
    }
}
