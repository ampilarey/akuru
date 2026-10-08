<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\VerifyIssuedCertificateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCertificateVerifyController extends Controller
{
    public function show(Request $request, string $publicId, VerifyIssuedCertificateAction $action): View
    {
        // The address stays unlocalized for scanners; the page speaks the
        // scanning browser's language when it is one of ours (BACKLOG C20, LT5c).
        app()->setLocale($request->getPreferredLanguage(['en', 'dv', 'ar']) ?? 'en');

        $face = $action->execute($publicId);

        abort_unless($face !== null, 404);

        return view('public.certificates.verify', [
            'certificate' => $face,
        ]);
    }
}
