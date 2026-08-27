<?php

namespace App\Domains\Pronunciation\Services;

use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use App\Domains\Pronunciation\DTOs\PronunciationPredictionResult;

/**
 * Rule 8: the platform with AI OFF. Every predict call reports the module
 * disabled; attempts fall to the human review queue untouched.
 */
class NullPronunciationPredictor implements PronunciationPredictionInterface
{
    public function predict(string $audioPath): PronunciationPredictionResult
    {
        return new PronunciationPredictionResult(
            success: false,
            error: 'Pronunciation AI is disabled.',
        );
    }
}
