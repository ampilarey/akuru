<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ListStaffChecklistAction;
use App\Domains\HR\Actions\SeedStaffChecklistAction;
use App\Domains\HR\Actions\ToggleOnboardingItemAction;
use App\Domains\HR\Enums\OnboardingKind;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $kind = $request->string('kind')->toString() ?: OnboardingKind::Onboarding->value;

        return Inertia::render('HR/Recruitment/Onboarding', [
            'kind' => $kind,
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'rows' => app(ListStaffChecklistAction::class)->execute(
                $request->integer('staff_profile_id') ?: null,
                $kind,
            )->values(),
        ]);
    }

    public function seed(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'kind' => ['required', Rule::enum(OnboardingKind::class)],
        ]);

        app(SeedStaffChecklistAction::class)->execute(
            (int) $data['staff_profile_id'],
            OnboardingKind::from($data['kind']),
        );

        return redirect()
            ->route('hr.onboarding.index', ['staff_profile_id' => $data['staff_profile_id'], 'kind' => $data['kind']])
            ->with('success', 'Checklist opened.');
    }

    public function toggle(Request $request, int $item): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'done' => ['required', 'boolean'],
        ]);

        app(ToggleOnboardingItemAction::class)->execute($item, (bool) $data['done'], $request->user()?->id);

        return redirect()->route('hr.onboarding.index')->with('success', 'Checklist updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListStaffChecklistAction::class)->execute(
            $request->integer('staff_profile_id') ?: null,
            $request->string('kind')->toString() ?: null,
        );

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'kind', 'item', 'done']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['staff_name'], $row['kind'], $row['item'], $row['done'] ? '1' : '0']);
            }
            fclose($out);
        }, 'staff-checklists.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
