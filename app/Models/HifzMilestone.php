<?php

namespace App\Models;

use App\Enums\Hifz\HifzMilestoneStatus;
use App\Enums\Hifz\HifzMilestoneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HifzMilestone extends Model
{
    protected $fillable = [
        'hifz_program_id', 'student_id', 'teacher_id', 'supervisor_id',
        'type', 'surah_number', 'juz_number', 'page_number', 'title',
        'completed_at', 'recommended_by', 'recommended_at',
        'supervisor_reviewed_by', 'supervisor_reviewed_at',
        'approved_by', 'approved_at', 'status', 'certificate_issued', 'note',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'type' => HifzMilestoneType::class,
        'status' => HifzMilestoneStatus::class,
        'completed_at' => 'datetime',
        'recommended_at' => 'datetime',
        'supervisor_reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'certificate_issued' => 'boolean',
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
}
