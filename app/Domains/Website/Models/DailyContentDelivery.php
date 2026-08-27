<?php

namespace App\Domains\Website\Models;

use App\Domains\Website\Enums\DailyDeliveryStatus;
use App\Domains\Website\Enums\DailySubscriptionChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyContentDelivery extends Model
{
    protected $fillable = [
        'subscription_id',
        'send_date',
        'channel',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'send_date' => 'date',
            'channel' => DailySubscriptionChannel::class,
            'status' => DailyDeliveryStatus::class,
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(DailyContentSubscription::class, 'subscription_id');
    }
}
