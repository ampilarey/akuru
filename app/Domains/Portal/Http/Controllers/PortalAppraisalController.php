<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\HR\Actions\AcknowledgeAppraisalAction;
use App\Domains\HR\Actions\ListAppraisalsAction;
use App\Domains\HR\Actions\ListCpdRecordsAction;
use App\Domains\HR\Actions\ListLessonObservationsAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalAppraisalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $profile = app(ResolveStaffProfileForUserAction::class)->execute((int) $request->user()->id);
        $staffId = $profile['id'] ?? null;

        return Inertia::render('Portal/Appraisals', [
            'staff' => $profile ? [
                'id' => $profile['id'],
                'name' => trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')),
            ] : null,
            'appraisals' => $staffId ? app(ListAppraisalsAction::class)->execute((int) $staffId)['rows'] : [],
            'observations' => $staffId
                ? app(ListLessonObservationsAction::class)->execute((int) $staffId)->filter(fn ($row) => $row['shared_with_staff'])->values()
                : collect(),
            'cpd' => $staffId ? app(ListCpdRecordsAction::class)->execute((int) $staffId)->values() : collect(),
        ]);
    }

    public function acknowledge(Request $request, int $appraisal): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $data = $request->validate([
            'staff_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        app(AcknowledgeAppraisalAction::class)->execute(
            $appraisal,
            (int) $request->user()->id,
            $data['staff_comment'] ?? null,
        );

        return redirect()->route('portal.appraisals')->with('success', 'Appraisal acknowledged.');
    }
}
