<?php

namespace App\Domains\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrayerCategory extends Model
{
    protected $fillable = [
        'id',
    ];

    public function islands(): HasMany
    {
        return $this->hasMany(PrayerIsland::class, 'category_id');
    }

    public function times(): HasMany
    {
        return $this->hasMany(PrayerTime::class, 'category_id');
    }
}
