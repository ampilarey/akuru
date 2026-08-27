<?php

namespace App\Domains\Website\Models;

use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use Illuminate\Database\Eloquent\Model;

class DailyContent extends Model
{
    protected $fillable = [
        'content_type',
        'publish_date',
        'status',
        'quran_ayah_id',
        'hadith_text_ar',
        'hadith_text_en',
        'hadith_text_dv',
        'hadith_collection',
        'hadith_number',
        'hadith_grading',
        'grading_source',
        'text_en',
        'text_dv',
        'text_ar',
        'attribution',
        'theme_tag',
        'notes_internal',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => DailyContentType::class,
            'status' => DailyContentStatus::class,
            'publish_date' => 'date',
        ];
    }
}
