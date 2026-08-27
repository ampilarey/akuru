<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Components\Arabic\Actions\ListArabicReferenceAction;
use App\Domains\Courses\Components\Quran\Actions\SummarizeQuranMistakesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F4 supervisor/dean oversight (§52.11–52.12 non-AI subset). Engine-owned on
 * purpose: it composes BOTH components — Quran aggregates by bare ids, Arabic
 * reference names — which no component may do itself (rule 3 isolation; this
 * is the documented engine→component direction).
 */
class CatalogQuranOversightController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        $summary = app(SummarizeQuranMistakesAction::class)->execute();
        $reference = app(ListArabicReferenceAction::class)->execute();
        $letters = collect($reference['letters'])->keyBy('id');
        $harakas = collect($reference['harakas'])->keyBy('id');

        $summary['wrong_letters'] = array_map(fn (array $row): array => $row + [
            'display_name' => $letters->get($row['letter_id'])['display_name'] ?? ('#'.$row['letter_id']),
            'arabic_character' => $letters->get($row['letter_id'])['arabic_character'] ?? '',
        ], $summary['wrong_letters']);
        $summary['wrong_harakas'] = array_map(fn (array $row): array => $row + [
            'display_name' => $harakas->get($row['haraka_id'])['display_name'] ?? ('#'.$row['haraka_id']),
            'symbol' => $harakas->get($row['haraka_id'])['symbol'] ?? '',
        ], $summary['wrong_harakas']);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($summary): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['section', 'key', 'count']);
                foreach ($summary['by_status'] as $status => $count) {
                    fputcsv($handle, ['submission_status', $status, $count]);
                }
                foreach ($summary['mistake_types'] as $row) {
                    fputcsv($handle, ['mistake_type', $row['type'], $row['count']]);
                }
                foreach ($summary['wrong_letters'] as $row) {
                    fputcsv($handle, ['wrong_letter', $row['display_name'], $row['count']]);
                }
                foreach ($summary['wrong_harakas'] as $row) {
                    fputcsv($handle, ['wrong_haraka', $row['display_name'], $row['count']]);
                }
                fclose($handle);
            }, 'quran-oversight.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Courses/Catalog/QuranOversight', $summary);
    }
}
