<?php

namespace App\Domains\Courses\Components\Quran\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Surah extends Model
{
    use HasFactory;

    protected $fillable = [
        'index',
        'arabic_name',
        'english_name',
        'transliteration',
        'ayah_count',
        'revelation_place',
        'juz_start',
        'juz_end',
        'description',
        'description_arabic',
        'description_dhivehi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
     * F5: `quranProgress()` and `recitationPractices()` pointed at
     * `Hifz\Models\QuranProgress` / `Hifz\Models\RecitationPractice`. A
     * reference dataset owned by the engine must not reach back into Hifz
     * (rule 3, and the Hifz-referenced-outside-Hifz guard), and nothing read
     * either relation. The `quran_progress.surah_number` and
     * `recitation_practices.surah_id` columns are untouched (rule 9).
     */

    /**
     * Scope to get only active surahs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by index
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('index');
    }

    /**
     * Get the full name with index
     */
    public function getFullNameAttribute()
    {
        return "{$this->index}. {$this->english_name} ({$this->arabic_name})";
    }

    /**
     * Get the Arabic full name with index
     */
    public function getFullNameArabicAttribute()
    {
        return "{$this->arabic_name} ({$this->index})";
    }

    /**
     * Get the Dhivehi full name with index
     */
    public function getFullNameDhivehiAttribute()
    {
        return "{$this->english_name} ({$this->index})";
    }
}
