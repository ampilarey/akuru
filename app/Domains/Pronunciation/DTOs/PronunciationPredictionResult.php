<?php

namespace App\Domains\Pronunciation\DTOs;

/** §51.11: the predict.py JSON, typed. */
class PronunciationPredictionResult
{
    public function __construct(
        public bool $success,
        public ?string $predictedLetter = null,
        public ?string $predictedHaraka = null,
        public ?float $letterConfidence = null,
        public ?float $harakaConfidence = null,
        public ?string $modelVersion = null,
        public ?string $error = null,
        public ?array $raw = null,
    ) {}
}
