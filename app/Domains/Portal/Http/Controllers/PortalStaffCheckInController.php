<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\HR\Actions\ListStaffAttendanceAction;
use App\Domains\HR\Actions\ResolveHrSettingsAction;
use App\Domains\HR\Actions\SelfCheckInStaffAttendanceAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalStaffCheckInController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $profile = app(ResolveStaffProfileForUserAction::class)->execute((int) $request->user()->id);
        $settings = app(ResolveHrSettingsAction::class)->execute();

        return Inertia::render('Portal/StaffCheckIn', [
            'enabled' => $settings['staff_self_checkin'],
            'staff' => $profile ? [
                'id' => $profile['id'],
                'name' => trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')),
                'department' => $profile['department'] ?? null,
            ] : null,
            'rows' => $profile
                ? app(ListStaffAttendanceAction::class)->execute([
                    'staff_profile_id' => (int) $profile['id'],
                ])->values()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        app(SelfCheckInStaffAttendanceAction::class)->execute(
            (int) $request->user()->id,
            $request->ip(),
        );

        return redirect()
            ->route('portal.staff-check-in')
            ->with('success', 'Checked in.');
    }
}
