<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplication extends Model
{
    protected $fillable = [
        'course_id',
        'full_name',
        'phone',
        'email',
        'guardian_name',
        'message',
        'source',
        'locale',
        'ip',
        'user_agent',
        'status',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
