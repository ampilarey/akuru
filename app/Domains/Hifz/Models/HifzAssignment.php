<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\Surah;

use App\Domains\People\Models\Teacher;

use App\Domains\People\Models\Student;

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

    public function newFromSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'new_from_surah_id');
    }

    public function newToSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'new_to_surah_id');
    }
}
