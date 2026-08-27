<?php

namespace App\Domains\Pronunciation\Http\Controllers;

use App\Domains\Pronunciation\Actions\ActivateAiModelVersionAction;
use App\Domains\Pronunciation\Actions\DecideTrainingSampleAction;
use App\Domains\Pronunciation\Actions\ExportApprovedSamplesAction;
use App\Domains\Pronunciation\Actions\GetAiDatasetStatsAction;
use App\Domains\Pronunciation\Actions\ListPronunciationQueuesAction;
use App\Domains\Pronunciation\Actions\SaveAiModelVersionAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * §51.16 steps 6–9: the dataset and model shelf. pronunciation.manage
 * gated; every model activation/rollback is audited by the actions.
 */
class AdminPronunciationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('pronunciation.manage'), 403);
        $queues = app(ListPronunciationQueuesAction::class)->execute();

        return Inertia::render('Pronunciation/Admin', [
            'pending_samples' => $queues['pending_samples'],
            'model_versions' => $queues['model_versions'],
            'stats' => app(GetAiDatasetStatsAction::class)->execute(),
            'ai_enabled' => $queues['ai_enabled'],
        ]);
    }

    public function decideSample(Request $request, int $sample): RedirectResponse
    {
        abort_unless($request->user()?->can('pronunciation.manage'), 403);
        $data = $request->validate([
            'approve' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        app(DecideTrainingSampleAction::class)->execute(
            $sample,
            (int) $request->user()->id,
            (bool) $data['approve'],
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Sample decided.');
    }

    public function export(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('pronunciation.manage'), 403);
        $result = app(ExportApprovedSamplesAction::class)->execute();

        return back()->with('success', "Exported {$result['count']} samples to {$result['manifest_path']}.");
    }

    public function storeVersion(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('pronunciation.manage'), 403);
        $data = $request->validate([
            'version_name' => 'required|string|max:100',
            'model_path' => 'required|string|max:500',
            'letter_labels_path' => 'nullable|string|max:500',
            'haraka_labels_path' => 'nullable|string|max:500',
            'training_sample_count' => 'nullable|integer|min:0',
            'validation_letter_accuracy' => 'nullable|numeric|min:0|max:1',
            'validation_haraka_accuracy' => 'nullable|numeric|min:0|max:1',
            'notes' => 'nullable|string|max:500',
        ]);

        app(SaveAiModelVersionAction::class)->execute($data, (int) $request->user()->id);

        return back()->with('success', 'Model version registered.');
    }

    public function activateVersion(Request $request, int $version): RedirectResponse
    {
        abort_unless($request->user()?->can('pronunciation.manage'), 403);
        $data = $request->validate(['rollback' => 'nullable|boolean']);

        app(ActivateAiModelVersionAction::class)->execute(
            $version,
            (int) $request->user()->id,
            (bool) ($data['rollback'] ?? false),
        );

        return back()->with('success', 'Model version activated.');
    }
}
