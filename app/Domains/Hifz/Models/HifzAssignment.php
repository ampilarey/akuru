<?php

namespace App\Domains\Hifz\Models;

use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HifzAssignment extends Model
{
    protected $fillable = [
        'hifz_program_id', 'student_id', 'teacher_id', 'supervisor_id',
        'assignment_date', 'new_from_surah_id', 'new_from_ayah',
        'new_to_surah_id', 'new_to_ayah', 'recent_revision_text',
        'old_revision_text', 'homework_note', 'status',
    ];

    protected $casts = [
        'assignment_date' => 'date',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(HifzProgram::class, 'hifz_program_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /*
     * F5: the Qur'an dataset (`surahs`, `quran_*`) now belongs to
     * Courses\Components\Quran, so Hifz cannot declare Eloquent relations to
     * it without importing another domain's models (rule 3). The foreign-key
     * columns are untouched — nothing was dropped (rule 9) — and readers go
     * through the `QuranReferenceReader` support contract instead.
     */
}
