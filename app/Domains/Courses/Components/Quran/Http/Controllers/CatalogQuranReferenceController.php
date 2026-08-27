<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\ListQuranReferenceAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogQuranReferenceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render(
            'Courses/Catalog/QuranReference',
            app(ListQuranReferenceAction::class)->execute(
                $request->filled('surah') ? (int) $request->input('surah') : null,
            ),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListQuranReferenceAction::class)->execute(
            $request->filled('surah') ? (int) $request->input('surah') : null,
        );

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['kind', 'id', 'surah_number', 'ayah_number', 'arabic_name', 'english_name', 'text']);
            foreach ($payload['surahs'] as $row) {
                fputcsv($handle, [
                    'surah',
                    $row['id'],
                    $row['index'],
                    $row['ayah_count'],
                    $row['arabic_name'],
                    $row['english_name'],
                    '',
                ]);
            }
            foreach ($payload['ayahs'] as $row) {
                fputcsv($handle, [
                    'ayah',
                    $row['id'],
                    $row['surah_number'],
                    $row['ayah_number'],
                    '',
                    '',
                    $row['text_uthmani'],
                ]);
            }
            fclose($handle);
        }, 'quran-reference.csv', ['Content-Type' => 'text/csv']);
    }
}
