<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\PromoteStudentsAction;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function create(Request $request): Response
    {
        $sourceYearId = $request->integer('source_year_id') ?: null;
        $targetYearId = $request->integer('target_year_id') ?: null;

        return Inertia::render('Academics/Promotion/Wizard', [
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'sourceYearId' => $sourceYearId,
            'targetYearId' => $targetYearId,
            'sourceClasses' => $sourceYearId
                ? ClassRoom::query()->where('academic_year_id', $sourceYearId)->get(['id', 'name', 'section'])
                : [],
            'targetClasses' => $targetYearId
                ? ClassRoom::query()->where('academic_year_id', $targetYearId)->get(['id', 'name', 'section'])
                : [],
            'report' => $request->session()->get('promotion_report'),
        ]);
    }

    public function dryRun(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $action = app(PromoteStudentsAction::class);
        $report = $action->execute(
            (int) $data['source_year_id'],
            (int) $data['target_year_id'],
            $this->intMap($data['class_map'] ?? []),
            $this->intMap($data['overrides'] ?? [], asString: true),
            true,
            (int) $request->user()->id,
        );
        $action->rememberDryRun((int) $data['source_year_id'], (int) $data['target_year_id']);

        return redirect()
            ->route('academics.promotion.create', [
                'source_year_id' => $data['source_year_id'],
                'target_year_id' => $data['target_year_id'],
            ])
            ->with('promotion_report', $report)
            ->with('success', 'Dry-run complete. Review the report before confirming.');
    }

    public function commit(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $report = app(PromoteStudentsAction::class)->execute(
            (int) $data['source_year_id'],
            (int) $data['target_year_id'],
            $this->intMap($data['class_map'] ?? []),
            $this->intMap($data['overrides'] ?? [], asString: true),
            false,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('academics.promotion.create')
            ->with('promotion_report', $report)
            ->with('success', 'Promotion committed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'source_year_id' => ['required', 'exists:academic_years,id'],
            'target_year_id' => ['required', 'exists:academic_years,id', 'different:source_year_id'],
            'class_map' => ['nullable', 'array'],
            'overrides' => ['nullable', 'array'],
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $map
     * @return array<int, int|string>
     */
    private function intMap(array $map, bool $asString = false): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $out[(int) $key] = $asString ? (string) $value : (int) $value;
        }

        return $out;
    }
}
