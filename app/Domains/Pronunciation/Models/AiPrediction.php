<?php

namespace App\Domains\Pronunciation\Models;

use Illuminate\Database\Eloquent\Model;

class AiPrediction extends Model
{
    protected $fillable = [
        'arabic_pronunciation_attempt_id',
        'quran_recitation_submission_id',
        'predicted_letter_id',
        'predicted_haraka_id',
        'predicted_letter_label',
        'predicted_haraka_label',
        'letter_confidence',
        'haraka_confidence',
        'is_letter_match',
        'is_haraka_match',
        'final_status',
        'raw_json',
        'model_version',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'letter_confidence' => 'decimal:4',
            'haraka_confidence' => 'decimal:4',
            'is_letter_match' => 'boolean',
            'is_haraka_match' => 'boolean',
        ];
    }
}
