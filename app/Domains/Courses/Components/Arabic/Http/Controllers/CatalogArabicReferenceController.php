<?php

namespace App\Domains\Courses\Components\Arabic\Http\Controllers;

use App\Domains\Courses\Components\Arabic\Actions\ListArabicReferenceAction;
use App\Domains\Courses\Components\Arabic\Actions\SaveArabicHarakahAction;
use App\Domains\Courses\Components\Arabic\Actions\SaveArabicLetterAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogArabicReferenceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/ArabicReference', app(ListArabicReferenceAction::class)->execute());
    }

    public function storeLetter(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveArabicLetterAction::class)->execute($request->all());

        return redirect()->route('catalog.arabic.index')->with('success', 'Letter saved.');
    }

    public function updateLetter(Request $request, int $letter): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveArabicLetterAction::class)->execute(
            $request->all(),
            ArabicLetter::query()->findOrFail($letter),
        );

        return redirect()->route('catalog.arabic.index')->with('success', 'Letter updated.');
    }

    public function storeHarakah(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveArabicHarakahAction::class)->execute($request->all());

        return redirect()->route('catalog.arabic.index')->with('success', 'Harakah saved.');
    }

    public function updateHarakah(Request $request, int $harakah): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveArabicHarakahAction::class)->execute(
            $request->all(),
            ArabicHarakah::query()->findOrFail($harakah),
        );

        return redirect()->route('catalog.arabic.index')->with('success', 'Harakah updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListArabicReferenceAction::class)->execute();

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['kind', 'id', 'key_name', 'glyph', 'display_name', 'is_active']);
            foreach ($payload['letters'] as $row) {
                fputcsv($handle, ['letter', $row['id'], $row['key_name'], $row['arabic_character'], $row['display_name'], $row['is_active'] ? 'yes' : 'no']);
            }
            foreach ($payload['harakas'] as $row) {
                fputcsv($handle, ['harakah', $row['id'], $row['key_name'], $row['symbol'], $row['display_name'], $row['is_active'] ? 'yes' : 'no']);
            }
            fclose($handle);
        }, 'arabic-reference.csv', ['Content-Type' => 'text/csv']);
    }
}
