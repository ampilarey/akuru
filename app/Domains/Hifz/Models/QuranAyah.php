<?php

namespace App\Domains\Hifz\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranAyah extends Model
{
    protected $fillable = [
        'quran_mushaf_id', 'quran_page_id', 'surah_number', 'ayah_number',
        'juz_number', 'page_number', 'text_uthmani', 'text_simple',
    ];

    public function mushaf(): BelongsTo
    {
        return $this->belongsTo(QuranMushaf::class, 'quran_mushaf_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(QuranPage::class, 'quran_page_id');
    }

    public function words(): HasMany
    {
        return $this->hasMany(QuranWord::class);
    }
}
