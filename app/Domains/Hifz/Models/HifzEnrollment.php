<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\Surah;
use App\Domains\Identity\Models\User;

use App\Domains\People\Models\Teacher;

use App\Domains\People\Models\Student;

use App\Enums\Hifz\HifzEnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HifzEnrollment extends Model
{
    protected $fillable = [
        'hifz_program_id', 'student_id', 'teacher_id', 'supervisor_id',
        'start_date', 'target_completion_date', 'current_surah_id',
        'current_juz', 'current_page', 'status', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'status' => HifzEnrollmentStatus::class,
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

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function currentSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'current_surah_id');
    }
}
