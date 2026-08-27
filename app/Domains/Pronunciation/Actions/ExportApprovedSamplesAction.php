<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\TrainingSample;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * §51.16 step 7: hand the approved dataset to the trainer as a manifest
 * (sample id, media path, verified letter/haraka key_names) and mark the
 * exported rows used_for_training. train.py consumes the manifest —
 * Laravel never trains, Python never queries the database.
 */
class ExportApprovedSamplesAction
{
    /**
     * @return array{count: int, manifest_path: string}
     */
    public function execute(): array
    {
        $samples = TrainingSample::query()->where('status', 'approved')->get();

        $letters = DB::table('arabic_letters')->pluck('key_name', 'id');
        $harakas = DB::table('arabic_harakas')->pluck('key_name', 'id');
        $media = DB::table('media_files')
            ->whereIn('id', $samples->pluck('audio_media_file_id'))
            ->get(['id', 'disk', 'path'])
            ->keyBy('id');

        $rows = $samples->map(fn (TrainingSample $sample) => [
            'sample_id' => $sample->id,
            'disk' => $media->get($sample->audio_media_file_id)?->disk,
            'path' => $media->get($sample->audio_media_file_id)?->path,
            'letter' => $letters->get($sample->verified_letter_id),
            'haraka' => $harakas->get($sample->verified_haraka_id),
        ])->filter(fn (array $row) => $row['path'] !== null)->values();

        $manifestPath = 'ai/pronunciation/approved_training_samples/manifest-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($manifestPath, json_encode($rows, JSON_PRETTY_PRINT));

        TrainingSample::query()
            ->whereIn('id', $rows->pluck('sample_id'))
            ->update(['status' => 'used_for_training']);

        return ['count' => $rows->count(), 'manifest_path' => $manifestPath];
    }
}
