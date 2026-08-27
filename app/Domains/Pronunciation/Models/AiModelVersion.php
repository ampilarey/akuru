<?php

namespace App\Domains\Pronunciation\Models;

use Illuminate\Database\Eloquent\Model;

class AiModelVersion extends Model
{
    protected $fillable = [
        'model_type',
        'version_name',
        'model_path',
        'letter_labels_path',
        'haraka_labels_path',
        'training_sample_count',
        'validation_letter_accuracy',
        'validation_haraka_accuracy',
        'is_active',
        'trained_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'validation_letter_accuracy' => 'decimal:4',
            'validation_haraka_accuracy' => 'decimal:4',
        ];
    }
}
