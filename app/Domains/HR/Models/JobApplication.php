<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\JobApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $fillable = [
        'job_posting_id',
        'name',
        'mobile',
        'email',
        'cv_document_id',
        'cover_note',
        'status',
        'stage_notes',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobApplicationStatus::class,
            'stage_notes' => 'array',
        ];
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }
}
