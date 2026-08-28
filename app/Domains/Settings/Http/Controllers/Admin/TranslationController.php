<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Settings\Actions\ListTranslationCatalogAction;
use App\Domains\Settings\Actions\SaveTranslationOverrideAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dhivehi translation editor — thin: authorize (route middleware) →
 * validate → action → response.
 */
class TranslationController extends Controller
{
    public function index(ListTranslationCatalogAction $list): Response
    {
        return Inertia::render('Settings/Translations', $list->execute());
    }

    public function save(Request $request, SaveTranslationOverrideAction $save): RedirectResponse
    {
        $data = $request->validate([
            'group' => 'required|string|max:40',
            'key' => 'required|string|max:191',
            'value' => 'nullable|string|max:2000',
        ]);

        $save->execute($data['group'], $data['key'], $data['value'] ?? null, (int) $request->user()->id);

        return back();
    }

    public function export(ListTranslationCatalogAction $list): StreamedResponse
    {
        $data = $list->execute();

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['group', 'key', 'english', 'file_dv', 'override_dv', 'suspect']);
            foreach ($data['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    fputcsv($out, [
                        $group['group'],
                        $item['key'],
                        $item['en'],
                        $item['file_dv'] ?? '',
                        $item['override'] ?? '',
                        $item['suspect'] ? 'yes' : 'no',
                    ]);
                }
            }
            fclose($out);
        }, 'dhivehi-translations.csv', ['Content-Type' => 'text/csv']);
    }
}
