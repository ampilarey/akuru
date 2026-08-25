<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\HR\Actions\ListLeaveBalancesAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalLeaveBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $profile = app(ResolveStaffProfileForUserAction::class)->execute((int) $request->user()->id);
        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        return Inertia::render('Portal/LeaveBalances', [
            'staff' => $profile ? [
                'id' => $profile['id'],
                'name' => trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')),
            ] : null,
            'rows' => $profile
                ? app(ListLeaveBalancesAction::class)->execute([
                    'staff_profile_id' => (int) $profile['id'],
                    'academic_year_id' => $year['id'] ?? null,
                ])->values()
                : collect(),
        ]);
    }
}
