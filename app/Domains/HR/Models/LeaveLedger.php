<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveLedger extends Model
{
    protected $table = 'leave_ledger';

    protected $fillable = [
        'entitlement_id',
        'request_id',
        'days',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:1',
        ];
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class, 'entitlement_id');
    }
}
