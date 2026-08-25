<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentExpiryNotice extends Model
{
    protected $fillable = [
        'document_id',
        'horizon_days',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }
}
