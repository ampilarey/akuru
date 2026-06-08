<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranPage extends Model
{
    protected $fillable = [
        'quran_mushaf_id', 'page_number', 'juz_number',
        'image_path', 'pdf_page_number', 'width', 'height',
    ];

    public function mushaf(): BelongsTo
    {
        return $this->belongsTo(QuranMushaf::class, 'quran_mushaf_id');
    }

    public function ayahs(): HasMany
    {
        return $this->hasMany(QuranAyah::class);
    }

    public function wordPositions(): HasMany
    {
        return $this->hasMany(QuranWordPosition::class);
    }
}
