<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Settings\Actions\ListFeatureWalkthroughAction;
use App\Domains\Settings\Actions\ListOperatorChecklistAction;
use App\Domains\Settings\Actions\ToggleOperatorCheckAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operator close-out checklist (docs/OPERATOR_CHECKLIST.md, in-app).
 * Thin: authorize (route middleware) → action → response.
 */
class OperationsController extends Controller
{
    public function index(ListOperatorChecklistAction $list): Response
    {
        return Inertia::render('Settings/Operations', $list->execute());
    }

    public function features(ListFeatureWalkthroughAction $list): Response
    {
        return Inertia::render('Settings/Features', $list->execute());
    }

    public function featuresExport(ListFeatureWalkthroughAction $list): StreamedResponse
    {
        $data = $list->execute();

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['section', 'item_key', 'label', 'where', 'checked', 'checked_by', 'checked_at']);
            foreach ($data['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $state = $data['checked'][$item['key']] ?? null;
                    fputcsv($out, [
                        $section['title'],
                        $item['key'],
                        $item['label'],
                        $item['where'],
                        $state !== null ? 'yes' : 'no',
                        $state['by'] ?? '',
                        $state['at'] ?? '',
                    ]);
                }
            }
            fclose($out);
        }, 'feature-walkthrough.csv', ['Content-Type' => 'text/csv']);
    }

    public function toggle(Request $request, string $item, ToggleOperatorCheckAction $toggle): RedirectResponse
    {
        $toggle->execute($item, (int) $request->user()->id);

        return back();
    }

    public function export(ListOperatorChecklistAction $list): StreamedResponse
    {
        $data = $list->execute();

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['section', 'item_key', 'label', 'checked', 'checked_by', 'checked_at']);
            foreach ($data['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $state = $data['checked'][$item['key']] ?? null;
                    fputcsv($out, [
                        $section['title'],
                        $item['key'],
                        $item['label'],
                        $state !== null ? 'yes' : 'no',
                        $state['by'] ?? '',
                        $state['at'] ?? '',
                    ]);
                }
            }
            fclose($out);
        }, 'operator-checklist.csv', ['Content-Type' => 'text/csv']);
    }
}
