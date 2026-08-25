<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Finance\Actions\ListPortalInvoicesAction;
use App\Domains\Finance\Actions\PayPortalInvoiceAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        $childIds = $children->pluck('id')->all();
        $requested = $request->integer('student_id') ?: null;
        if ($requested && ! in_array($requested, $childIds, true)) {
            abort(403);
        }
        $studentId = $requested ?: ($childIds[0] ?? null);

        return Inertia::render('Portal/Invoices', [
            'children' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
            ])->values(),
            'studentId' => $studentId,
            'invoices' => $studentId
                ? app(ListPortalInvoicesAction::class)->execute([$studentId])->values()
                : collect(),
        ]);
    }

    public function pay(Request $request, int $invoice): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $childIds = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $mode = $request->validate(['mode' => ['nullable', 'in:full,installment']])['mode'] ?? 'full';
        $result = app(PayPortalInvoiceAction::class)->execute($invoice, (int) $request->user()->id, $childIds, $mode);

        if ($result['redirect_url']) {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()->route('portal.invoices')
            ->with('error', $result['error'] ?? 'Payment initiation failed.');
    }
}
