<?php

namespace App\Domains\Offerings\Models;

use App\Domains\Offerings\Enums\SessionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOfferingSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_offering_id',
        'academic_year_id',
        'term_id',
        'title',
        'description',
        'session_type',
        'starts_at',
        'ends_at',
        'timezone',
        'location_name',
        'location_address',
        'online_meeting_url',
        'online_meeting_provider',
        'teacher_user_id',
        'is_required',
        'recording_url',
        'materials',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_type' => SessionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_required' => 'boolean',
            'materials' => 'array',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'course_offering_session_id');
    }
}
