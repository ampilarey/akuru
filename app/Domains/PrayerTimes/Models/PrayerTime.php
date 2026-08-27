<?php

namespace App\Domains\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerTime extends Model
{
    protected $fillable = [
        'category_id',
        'day_of_year',
        'fajr',
        'sunrise',
        'dhuhr',
        'asr',
        'maghrib',
        'isha',
    ];

    protected function casts(): array
    {
        return [
            'day_of_year' => 'integer',
            'fajr' => 'integer',
            'sunrise' => 'integer',
            'dhuhr' => 'integer',
            'asr' => 'integer',
            'maghrib' => 'integer',
            'isha' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PrayerCategory::class, 'category_id');
    }
}
