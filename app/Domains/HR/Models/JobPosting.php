<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\JobPostingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    protected $fillable = [
        'title',
        'title_arabic',
        'title_dhivehi',
        'description',
        'requirements',
        'department',
        'employment_type',
        'closes_at',
        'status',
        'public',
    ];

    protected function casts(): array
    {
        return [
            'closes_at' => 'date',
            'status' => JobPostingStatus::class,
            'public' => 'boolean',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
