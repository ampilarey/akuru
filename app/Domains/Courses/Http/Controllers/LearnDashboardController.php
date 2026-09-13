<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListStudentDashboardAction;
use App\Domains\Courses\Actions\ServeStudentCertificateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LearnDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render('Courses/Learn/Dashboard', app(ListStudentDashboardAction::class)->execute((int) $request->user()->id));
    }

    /**
     * SPEC §24 "Certificates". §39 issued them and every route to one was
     * staff-only, so a student was told they had earned a certificate and had
     * no way to open it. The Action owns the gate — own certificate, not
     * revoked — because that is the rule, not a controller detail.
     */
    public function certificate(Request $request, int $certificate): HttpResponse
    {
        $file = app(ServeStudentCertificateAction::class)->execute(
            $certificate,
            $request->user()?->id !== null ? (int) $request->user()->id : null,
        );

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['filename'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
