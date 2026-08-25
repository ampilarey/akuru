<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use Illuminate\Database\Eloquent\Model;

class SchoolRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'type',
        'requester_id',
        'regarding_type',
        'regarding_id',
        'payload',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => SchoolRequestType::class,
            'status' => SchoolRequestStatus::class,
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
