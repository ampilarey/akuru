<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Portal\Actions\ComposeAccountHomeAction;
use App\Domains\Portal\Actions\ComposeMyEnrolmentsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Navigation\ResolveWorkspacesAction;
use App\Support\Navigation\WorkspaceMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * *My account* and *My enrolments*, inside the shell (docs/SIGN_IN_PLAN.md
 * ID2b). They replace the old course portal (`/portal/dashboard` and its
 * enrolments, payments, certificates and profile pages), the public course
 * dashboard a person with no role landed on, and the Blade My enrolments
 * page — all in the website's layout, so signing in took a person back to
 * the website rather than into the app (findings F2, F5).
 */
class MyAccountController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        // The home of the account workspace alone. Anyone who holds another —
        // a family, a learner, a shop — is sent to that one's home, as the
        // family portal sends a vendor to their shop.
        $held = array_column(app(ResolveWorkspacesAction::class)->execute($user)['list'], 'key');
        if ($held !== [WorkspaceMap::ACCOUNT]) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Portal/AccountHome', [
            ...app(ComposeAccountHomeAction::class)->execute((int) $user->id, (bool) $user->force_password_change),
            't' => trans('account'),
        ]);
    }

    public function enrolments(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('Portal/MyEnrolments', [
            ...app(ComposeMyEnrolmentsAction::class)->execute((int) $user->id),
            'export_href' => route('my.enrollments.export', [], false),
            'browse_href' => '/learn/catalog',
            't' => trans('account'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $rows = app(ComposeMyEnrolmentsAction::class)->execute((int) $user->id);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['section', 'course_or_reference', 'for', 'status', 'payment', 'amount', 'currency', 'date']);
            foreach ($rows['enrolments'] as $row) {
                Csv::put($out, ['enrolment', $row['course'], $row['student'], $row['state'], $row['payment'], '', '', $row['date'] ?? '']);
            }
            foreach ($rows['payments'] as $row) {
                Csv::put($out, ['payment', $row['reference'], $row['for'], $row['status'], '', $row['amount'], $row['currency'], $row['date'] ?? '']);
            }
            fclose($out);
        }, 'my-enrolments.csv', ['Content-Type' => 'text/csv']);
    }
}
