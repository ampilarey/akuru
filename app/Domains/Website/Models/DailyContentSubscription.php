<?php

namespace App\Domains\Website\Models;

use App\Domains\Website\Enums\DailySubscriptionChannel;
use App\Domains\Website\Enums\DailySubscriptionLanguage;
use App\Domains\Website\Enums\DailySubscriptionStatus;
use App\Domains\Website\Enums\DailyUnsubscribeReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyContentSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'content_types',
        'language',
        'send_time',
        'status',
        'unsubscribe_token',
        'unsubscribed_at',
        'unsubscribe_reason',
    ];

    protected function casts(): array
    {
        return [
            'channel' => DailySubscriptionChannel::class,
            'content_types' => 'array',
            'language' => DailySubscriptionLanguage::class,
            'status' => DailySubscriptionStatus::class,
            'unsubscribed_at' => 'datetime',
            'unsubscribe_reason' => DailyUnsubscribeReason::class,
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DailyContentDelivery::class, 'subscription_id');
    }
}
