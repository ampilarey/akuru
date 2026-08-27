<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\AiModelVersion;
use App\Domains\Pronunciation\Models\AiModelVersionEvent;
use Illuminate\Validation\ValidationException;

/**
 * §51.18: register a trained model as a NEW version — old versions are
 * kept, the active one is never overwritten. Registration is audited.
 */
class SaveAiModelVersionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $byUserId = null): AiModelVersion
    {
        $versionName = trim((string) ($data['version_name'] ?? ''));
        if ($versionName === '') {
            throw ValidationException::withMessages(['version_name' => 'Version name is required.']);
        }

        $version = AiModelVersion::query()->create([
            'model_type' => $data['model_type'] ?? 'arabic_pronunciation',
            'version_name' => $versionName,
            'model_path' => (string) $data['model_path'],
            'letter_labels_path' => $data['letter_labels_path'] ?? null,
            'haraka_labels_path' => $data['haraka_labels_path'] ?? null,
            'training_sample_count' => (int) ($data['training_sample_count'] ?? 0),
            'validation_letter_accuracy' => $data['validation_letter_accuracy'] ?? null,
            'validation_haraka_accuracy' => $data['validation_haraka_accuracy'] ?? null,
            'is_active' => false,
            'trained_by_user_id' => $byUserId,
            'notes' => $data['notes'] ?? null,
        ]);

        AiModelVersionEvent::query()->create([
            'ai_model_version_id' => $version->id,
            'action' => 'registered',
            'by_user_id' => $byUserId,
        ]);

        return $version;
    }
}
