<?php

namespace App\Domains\Pronunciation\Services;

use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use App\Domains\Pronunciation\DTOs\PronunciationPredictionResult;

/**
 * §51.9/§51.11: shells out to the LOCAL predict.py (never a cloud API);
 * predict.py prints JSON only. Any process or JSON failure comes back as
 * an error result — callers degrade to the human queue, never crash.
 */
class LocalPythonPronunciationPredictor implements PronunciationPredictionInterface
{
    public function predict(string $audioPath): PronunciationPredictionResult
    {
        $command = [
            (string) config('ai.python_bin', 'python3'),
            (string) config('ai.predict_script'),
            $audioPath,
        ];

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (! is_resource($process)) {
            return new PronunciationPredictionResult(success: false, error: 'Could not start the prediction process.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $decoded = json_decode(trim($stdout), true);
        if ($exit !== 0 || ! is_array($decoded)) {
            return new PronunciationPredictionResult(
                success: false,
                error: 'Prediction failed: '.trim($stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'exit '.$exit)),
            );
        }

        return new PronunciationPredictionResult(
            success: (bool) ($decoded['success'] ?? false),
            predictedLetter: $decoded['predicted_letter'] ?? null,
            predictedHaraka: $decoded['predicted_haraka'] ?? null,
            letterConfidence: isset($decoded['letter_confidence']) ? (float) $decoded['letter_confidence'] : null,
            harakaConfidence: isset($decoded['haraka_confidence']) ? (float) $decoded['haraka_confidence'] : null,
            modelVersion: $decoded['model_version'] ?? null,
            error: $decoded['error'] ?? null,
            raw: $decoded,
        );
    }
}
