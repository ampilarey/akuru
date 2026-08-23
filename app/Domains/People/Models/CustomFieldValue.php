<?php

namespace App\Domains\People\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    protected $fillable = [
        'definition_id',
        'entity_type',
        'entity_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }

    public function rawValue(): mixed
    {
        $payload = $this->value;

        return is_array($payload) && array_key_exists('v', $payload)
            ? $payload['v']
            : $payload;
    }
}
