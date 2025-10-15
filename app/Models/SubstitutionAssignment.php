<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubstitutionAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'substitution_request_id',
        'substitute_teacher_id',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the substitution request
     */
    public function substitutionRequest(): BelongsTo
    {
        return $this->belongsTo(SubstitutionRequest::class);
    }

    /**
     * Get the substitute teacher
     */
    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    /**
     * Get the user who assigned the substitution
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // When a substitution is assigned, update the request status
        static::created(function ($assignment) {
            $assignment->substitutionRequest->update(['status' => 'assigned']);
        });

        // When a substitution is deleted, update the request status back to open
        static::deleted(function ($assignment) {
            $assignment->substitutionRequest->update(['status' => 'open']);
        });
    }
}