<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\TrainingSample;
use Illuminate\Support\Facades\DB;

/**
 * §51.14: how much verified data each letter+haraka cell holds — the
 * admin's guide for what to collect before the next training batch.
 */
class GetAiDatasetStatsAction
{
    /**
     * @return array{totals: array<string, int>, cells: list<array<string, mixed>>}
     */
    public function execute(): array
    {
        $letters = DB::table('arabic_letters')->pluck('key_name', 'id');
        $harakas = DB::table('arabic_harakas')->pluck('key_name', 'id');

        $cells = TrainingSample::query()
            ->whereIn('status', ['approved', 'used_for_training'])
            ->selectRaw('verified_letter_id, verified_haraka_id, COUNT(*) as samples')
            ->groupBy('verified_letter_id', 'verified_haraka_id')
            ->get()
            ->map(fn ($row) => [
                'letter' => $letters->get($row->verified_letter_id) ?? (string) $row->verified_letter_id,
                'haraka' => $harakas->get($row->verified_haraka_id) ?? (string) $row->verified_haraka_id,
                'samples' => (int) $row->samples,
            ])->values()->all();

        $totals = TrainingSample::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        return ['totals' => $totals, 'cells' => $cells];
    }
}
