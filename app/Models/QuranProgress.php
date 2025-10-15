<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuranProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'surah_name',
        'surah_name_arabic',
        'surah_number',
        'from_ayah',
        'to_ayah',
        'type',
        'status',
        'accuracy_percentage',
        'teacher_notes',
        'teacher_notes_arabic',
        'date_completed',
        'last_revision_date',
        'revision_count',
    ];

    protected $casts = [
        'date_completed' => 'date',
        'last_revision_date' => 'date',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the surah information
     */
    public function surah()
    {
        return $this->belongsTo(Surah::class, 'surah_number', 'index');
    }

    /**
     * Get recitation practices for this progress
     */
    public function recitationPractices()
    {
        return $this->hasMany(RecitationPractice::class, 'student_id', 'student_id')
            ->where('surah_id', $this->surah?->id);
    }

    // Helper methods
    public function getProgressPercentageAttribute()
    {
        if ($this->to_ayah && $this->from_ayah) {
            return (($this->to_ayah - $this->from_ayah + 1) / $this->getSurahAyahCount()) * 100;
        }
        return 0;
    }

    public function getSurahAyahCount()
    {
        // This would typically come from a surah data table
        // For now, we'll use a simple mapping
        $surahAyahCounts = [
            1 => 7,    // Al-Fatiha
            2 => 286,  // Al-Baqarah
            3 => 200,  // Ali Imran
            // ... add more as needed
        ];

        return $surahAyahCounts[$this->surah_number] ?? 1;
    }

    public function getAyahRangeAttribute()
    {
        if ($this->from_ayah && $this->to_ayah) {
            return $this->from_ayah . '-' . $this->to_ayah;
        }
        return 'Full Surah';
    }
}
