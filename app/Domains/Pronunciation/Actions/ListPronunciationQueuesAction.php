<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\AiModelVersion;
use App\Domains\Pronunciation\Models\AiPrediction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use App\Domains\Pronunciation\Models\TrainingSample;
use Illuminate\Support\Facades\DB;

/**
 * The two human queues: attempts awaiting a teacher's ear (with the AI's
 * opinion when there is one) and pending training samples awaiting
 * approval — plus the model-version shelf for the admin screen.
 */
class ListPronunciationQueuesAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $letters = DB::table('arabic_letters')->get(['id', 'key_name', 'arabic_character'])->keyBy('id');
        $harakas = DB::table('arabic_harakas')->get(['id', 'key_name', 'symbol'])->keyBy('id');

        $attempts = ArabicPronunciationAttempt::query()
            ->where('teacher_review_required', true)
            ->whereIn('status', ['submitted', 'ai_checked'])
            ->orderBy('created_at')
            ->limit(100)
            ->get();
        $predictions = AiPrediction::query()
            ->whereIn('id', $attempts->pluck('ai_prediction_id')->filter())
            ->get()
            ->keyBy('id');

        $reviewQueue = $attempts->map(fn (ArabicPronunciationAttempt $attempt) => [
            'id' => $attempt->id,
            'expected_letter' => $letters->get($attempt->expected_letter_id)?->key_name,
            'expected_letter_id' => $attempt->expected_letter_id,
            'expected_haraka' => $harakas->get($attempt->expected_haraka_id)?->key_name,
            'expected_haraka_id' => $attempt->expected_haraka_id,
            'has_audio' => $attempt->audio_media_file_id !== null,
            'audio_media_file_id' => $attempt->audio_media_file_id,
            'status' => $attempt->status,
            'submitted_at' => $attempt->created_at?->toDateTimeString(),
            'ai' => ($prediction = $predictions->get($attempt->ai_prediction_id)) ? [
                'letter' => $prediction->predicted_letter_label,
                'haraka' => $prediction->predicted_haraka_label,
                'letter_confidence' => $prediction->letter_confidence !== null ? (float) $prediction->letter_confidence : null,
                'haraka_confidence' => $prediction->haraka_confidence !== null ? (float) $prediction->haraka_confidence : null,
                'final_status' => $prediction->final_status,
            ] : null,
        ])->values()->all();

        $samples = TrainingSample::query()
            ->where('status', 'pending_review')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (TrainingSample $sample) => [
                'id' => $sample->id,
                'letter' => $letters->get($sample->verified_letter_id)?->key_name,
                'haraka' => $harakas->get($sample->verified_haraka_id)?->key_name,
                'notes' => $sample->notes,
                'created_at' => $sample->created_at?->toDateString(),
            ])->values()->all();

        $versions = AiModelVersion::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiModelVersion $version) => [
                'id' => $version->id,
                'version_name' => $version->version_name,
                'model_type' => $version->model_type,
                'training_sample_count' => $version->training_sample_count,
                'letter_accuracy' => $version->validation_letter_accuracy !== null ? (float) $version->validation_letter_accuracy : null,
                'haraka_accuracy' => $version->validation_haraka_accuracy !== null ? (float) $version->validation_haraka_accuracy : null,
                'is_active' => (bool) $version->is_active,
                'registered_at' => $version->created_at?->toDateString(),
            ])->values()->all();

        return [
            'review_queue' => $reviewQueue,
            'pending_samples' => $samples,
            'model_versions' => $versions,
            'letters' => $letters->values()->map(fn ($row) => ['id' => $row->id, 'key_name' => $row->key_name, 'char' => $row->arabic_character])->all(),
            'harakas' => $harakas->values()->map(fn ($row) => ['id' => $row->id, 'key_name' => $row->key_name, 'symbol' => $row->symbol])->all(),
            'ai_enabled' => (bool) config('ai.pronunciation_enabled'),
        ];
    }
}
