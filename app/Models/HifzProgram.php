<?php

namespace App\Models;

use App\Domains\People\Models\Teacher;

use App\Enums\Hifz\HifzProgramStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HifzProgram extends Model
{
    protected $fillable = [
        'name', 'description', 'academic_year_id', 'class_id',
        'dean_id', 'supervisor_id', 'default_teacher_id', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => HifzProgramStatus::class,
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function dean(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dean_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function defaultTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'default_teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(HifzEnrollment::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(HifzSession::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(HifzMilestone::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(HifzAssignment::class);
    }
}
