<?php

namespace App\Models;

use App\Enums\Hifz\HifzMistakeSeverity;
use App\Enums\Hifz\HifzMistakeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HifzMistake extends Model
{
    protected $fillable = [
        'hifz_session_record_id', 'student_id', 'teacher_id', 'supervisor_id',
        'quran_page_id', 'quran_ayah_id', 'quran_word_id',
        'surah_number', 'ayah_number', 'word_number',
        'mistake_type', 'severity', 'teacher_note', 'supervisor_note', 'parent_visible',
    ];

    protected $casts = [
        'mistake_type' => HifzMistakeType::class,
        'severity' => HifzMistakeSeverity::class,
        'parent_visible' => 'boolean',
    ];

    public function sessionRecord(): BelongsTo
    {
        return $this->belongsTo(HifzSessionRecord::class, 'hifz_session_record_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function quranPage(): BelongsTo
    {
        return $this->belongsTo(QuranPage::class);
    }

    public function quranAyah(): BelongsTo
    {
        return $this->belongsTo(QuranAyah::class);
    }

    public function quranWord(): BelongsTo
    {
        return $this->belongsTo(QuranWord::class);
    }
}
