<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\QuranPage;
use App\Domains\Hifz\Models\QuranWord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuranWordPosition extends Model
{
    protected $fillable = [
        'quran_mushaf_id', 'quran_page_id', 'quran_word_id', 'page_number',
        'x', 'y', 'width', 'height', 'coordinate_type',
    ];

    protected $casts = [
        'x' => 'decimal:4',
        'y' => 'decimal:4',
        'width' => 'decimal:4',
        'height' => 'decimal:4',
    ];

    public function mushaf(): BelongsTo
    {
        return $this->belongsTo(QuranMushaf::class, 'quran_mushaf_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(QuranPage::class, 'quran_page_id');
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(QuranWord::class, 'quran_word_id');
    }
}
