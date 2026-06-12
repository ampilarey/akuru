<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\QuranWordPosition;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranWord extends Model
{
    protected $fillable = [
        'quran_mushaf_id', 'quran_ayah_id', 'surah_number', 'ayah_number',
        'word_number', 'word_text', 'word_text_simple', 'page_number',
    ];

    public function mushaf(): BelongsTo
    {
        return $this->belongsTo(QuranMushaf::class, 'quran_mushaf_id');
    }

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(QuranAyah::class, 'quran_ayah_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(QuranWordPosition::class);
    }
}
