<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    /**
     * Get the Quran progress records for this surah
     */
    public function quranProgress()
    {
        return $this->hasMany(QuranProgress::class);
    }

    /**
     * Get the recitation practices for this surah
     */
    public function recitationPractices()
    {
        return $this->hasMany(RecitationPractice::class);
    }

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