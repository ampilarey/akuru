<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\HR\Actions\ListPayslipsAction;
use App\Domains\HR\Actions\ResolvePayrollSettingsAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalPayslipController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $profile = app(ResolveStaffProfileForUserAction::class)->execute((int) $request->user()->id);

        return Inertia::render('Portal/Payslips', [
            'enabled' => app(ResolvePayrollSettingsAction::class)->execute()['enabled'],
            'staff' => $profile ? [
                'id' => $profile['id'],
                'name' => trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')),
            ] : null,
            'rows' => $profile
                ? app(ListPayslipsAction::class)->execute(null, (int) $profile['id'])->values()
                : collect(),
        ]);
    }
}
