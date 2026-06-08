<?php

namespace App\Models;

use App\Enums\Hifz\HifzSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HifzSession extends Model
{
    protected $fillable = [
        'hifz_program_id', 'teacher_id', 'supervisor_id', 'session_date',
        'start_time', 'end_time', 'title', 'general_note', 'supervisor_note',
        'reviewed_by', 'reviewed_at', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'session_date' => 'date',
        'reviewed_at' => 'datetime',
        'status' => HifzSessionStatus::class,
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(HifzProgram::class, 'hifz_program_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(HifzSessionRecord::class);
    }
}
