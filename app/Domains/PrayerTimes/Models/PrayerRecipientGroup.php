<?php

namespace App\Domains\PrayerTimes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrayerRecipientGroup extends Model
{
    protected $fillable = [
        'name_en',
        'name_dv',
        'name_ar',
        'description',
        'member_refs',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'member_refs' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(PrayerBroadcast::class, 'recipient_group_id');
    }
}
