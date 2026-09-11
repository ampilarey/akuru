<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Circulation\Actions\ListLoansAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What my child has out from the library.
 *
 * The family half of E16, and the reason overdue notices stop being a surprise:
 * a parent who can see a due date can act on it. Cross-domain by Action only
 * (rule 3), scope from who the viewer is rather than the request.
 */
class PortalLoanController extends Controller
{
    public function index(Request $request): Response
    {
        $ids = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Inertia::render('Portal/Loans', [
            'loans' => app(ListLoansAction::class)->forStudents($ids),
        ]);
    }
}
