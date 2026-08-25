<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorRecordAudit extends Model
{
    protected $fillable = [
        'behavior_record_id',
        'actor_id',
        'action',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BehaviorRecord::class, 'behavior_record_id');
    }
}
