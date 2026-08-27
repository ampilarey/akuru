<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Enums\QuranTranslationLanguage;
use Illuminate\Database\Eloquent\Model;

class QuranTranslation extends Model
{
    protected $fillable = [
        'quran_ayah_id',
        'language',
        'text',
        'source_name',
        'source_note',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'language' => QuranTranslationLanguage::class,
        ];
    }
}
