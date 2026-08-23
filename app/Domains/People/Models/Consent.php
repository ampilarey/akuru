<?php

namespace App\Domains\People\Models;

use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentSource;
use App\Domains\People\Enums\ConsentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    protected $fillable = [
        'person_type',
        'person_id',
        'consent_type',
        'granted',
        'granted_by',
        'granted_at',
        'revoked_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'person_type' => ConsentPersonType::class,
            'consent_type' => ConsentType::class,
            'source' => ConsentSource::class,
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.user'), 'granted_by');
    }
}
