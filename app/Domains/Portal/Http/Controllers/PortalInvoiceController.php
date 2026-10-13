<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Finance\Actions\ListPortalInvoicesAction;
use App\Domains\Finance\Actions\PayPortalInvoiceAction;
use App\Domains\Portal\Actions\ResolvePortalHouseholdAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $people = $this->people($request);
        $childIds = array_column($people, 'id');
        $requested = $request->integer('student_id') ?: null;
        if ($requested && ! in_array($requested, $childIds, true)) {
            abort(403);
        }
        $studentId = $requested ?: ($childIds[0] ?? null);

        return Inertia::render('Portal/Invoices', [
            'children' => array_map(fn (array $person): array => [
                'id' => $person['id'],
                'name' => $person['name'],
            ], $people),
            'studentId' => $studentId,
            'invoices' => $studentId
                ? app(ListPortalInvoicesAction::class)->execute([$studentId])->values()
                : collect(),
            't' => Phrases::once('portal'),
        ]);
    }

    public function pay(Request $request, int $invoice): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $childIds = array_column($this->people($request), 'id');

        $mode = $request->validate(['mode' => ['nullable', 'in:full,installment']])['mode'] ?? 'full';
        $result = app(PayPortalInvoiceAction::class)->execute($invoice, (int) $request->user()->id, $childIds, $mode);

        if ($result['redirect_url']) {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()->route('portal.invoices')
            ->with('error', $result['error'] ?? __('portal.error_payment_failed'));
    }

    /**
     * @return list<array{id: int, name: string, relationship: string}>
     */
    private function people(Request $request): array
    {
        $household = app(ResolvePortalHouseholdAction::class);

        return $household->people((int) $request->user()->id, $household->audience($request->user()));
    }
}
