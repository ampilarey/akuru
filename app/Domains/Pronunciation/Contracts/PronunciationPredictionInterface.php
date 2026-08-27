<?php

namespace App\Domains\Pronunciation\Contracts;

use App\Domains\Pronunciation\DTOs\PronunciationPredictionResult;

/**
 * §51.14: the ONE way anything asks the pronunciation AI a question.
 * Implementations are local/offline (§51.9); Courses/Activities/Progress
 * never call Python directly. With the feature flag off the container
 * binds a null predictor and the platform runs human-only (rule 8).
 */
interface PronunciationPredictionInterface
{
    public function predict(string $audioPath): PronunciationPredictionResult;
}
