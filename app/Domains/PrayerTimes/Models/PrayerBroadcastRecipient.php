<?php

namespace App\Domains\PrayerTimes\Models;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerBroadcastRecipient extends Model
{
    protected $fillable = [
        'prayer_broadcast_id',
        'contact_ref',
        'phone',
        'status',
        'message_body',
        'cost',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'contact_ref' => 'array',
            'status' => PrayerBroadcastRecipientStatus::class,
            'cost' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(PrayerBroadcast::class, 'prayer_broadcast_id');
    }
}
