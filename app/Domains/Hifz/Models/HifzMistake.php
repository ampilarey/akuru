<?php

namespace App\Domains\Hifz\Models;

use App\Domains\People\Models\Student;
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

    /*
     * F5: the Qur'an dataset (`surahs`, `quran_*`) now belongs to
     * Courses\Components\Quran, so Hifz cannot declare Eloquent relations to
     * it without importing another domain's models (rule 3). The foreign-key
     * columns are untouched — nothing was dropped (rule 9) — and readers go
     * through the `QuranReferenceReader` support contract instead.
     */
}
