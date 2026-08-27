<?php

namespace App\Domains\Pronunciation\Providers;

use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use App\Domains\Pronunciation\Services\LocalPythonPronunciationPredictor;
use App\Domains\Pronunciation\Services\NullPronunciationPredictor;
use Illuminate\Support\ServiceProvider;

class PronunciationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Arabic B (§51.15, rule 8): the flag decides which predictor the
        // contract resolves to — OFF binds the null predictor and the whole
        // platform runs human-only.
        $this->app->bind(PronunciationPredictionInterface::class, function () {
            return config('ai.pronunciation_enabled')
                ? new LocalPythonPronunciationPredictor
                : new NullPronunciationPredictor;
        });
    }

    public function boot(): void
    {
        //
    }
}
