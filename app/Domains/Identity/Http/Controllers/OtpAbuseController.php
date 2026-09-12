<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\ListOtpAbuseEventsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPEC §32's "admin review" surface for OTP abuse events.
 *
 * Super-admin only, matching the rest of `admin/users`: these rows say which
 * contacts have been hammering the OTP endpoints, and that is a security log,
 * not a report.
 */
class OtpAbuseController extends Controller
{
    public function index(Request $request, ListOtpAbuseEventsAction $list): Response
    {
        return Inertia::render('Identity/OtpAbuse', $list->execute((int) $request->input('days', 7)));
    }

    public function export(Request $request, ListOtpAbuseEventsAction $list): StreamedResponse
    {
        $rows = $list->rows((int) $request->input('days', 7));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'otp-abuse-events.csv', ['Content-Type' => 'text/csv']);
    }
}
