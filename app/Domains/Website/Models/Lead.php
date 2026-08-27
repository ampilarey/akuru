<?php

namespace App\Domains\Website\Models;

use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'mobile',
        'email',
        'source',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
        ];
    }
}
