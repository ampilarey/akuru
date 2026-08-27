<?php

namespace App\Domains\Website\Models;

use App\Domains\Website\Enums\FunnelEventName;
use Illuminate\Database\Eloquent\Model;

class FunnelEvent extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'source',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'name' => FunnelEventName::class,
            'meta' => 'array',
        ];
    }
}
