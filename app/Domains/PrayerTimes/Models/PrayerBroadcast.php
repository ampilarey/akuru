<?php

namespace App\Domains\PrayerTimes\Models;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastLanguage;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastMode;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrayerBroadcast extends Model
{
    protected $fillable = [
        'mode',
        'island_id',
        'date_from',
        'date_to',
        'scheduled_at',
        'status',
        'created_by',
        'confirmed_by',
        'message_template',
        'recipient_group_id',
        'recipient_refs',
        'language',
        'sent_count',
        'failed_count',
        'estimated_cost',
        'preview_snapshot',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'mode' => PrayerBroadcastMode::class,
            'status' => PrayerBroadcastStatus::class,
            'language' => PrayerBroadcastLanguage::class,
            'date_from' => 'date',
            'date_to' => 'date',
            'scheduled_at' => 'datetime',
            'message_template' => 'array',
            'recipient_refs' => 'array',
            'preview_snapshot' => 'array',
            'estimated_cost' => 'decimal:2',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(PrayerIsland::class, 'island_id');
    }

    public function recipientGroup(): BelongsTo
    {
        return $this->belongsTo(PrayerRecipientGroup::class, 'recipient_group_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PrayerBroadcastRecipient::class);
    }
}
