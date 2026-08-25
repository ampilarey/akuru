<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Models\Payslip;
use App\Domains\Media\Actions\ReadDocumentContentAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayslipDocumentController extends Controller
{
    public function show(Request $request, Payslip $payslip): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $owns = app(ResolveStaffProfileForUserAction::class)->execute((int) $user->id);
        $isOwner = $owns && (int) $owns['id'] === (int) $payslip->staff_profile_id;
        abort_unless($isOwner || $user->can('payroll.run') || $user->can('hr.manage'), 403);
        abort_unless($payslip->document_id, 404);

        $document = app(ReadDocumentContentAction::class)->execute((int) $payslip->document_id);
        abort_unless($document !== null, 404);

        return response($document['content'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
