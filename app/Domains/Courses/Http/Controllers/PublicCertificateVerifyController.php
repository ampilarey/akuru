<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\VerifyIssuedCertificateAction;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PublicCertificateVerifyController extends Controller
{
    public function show(string $publicId, VerifyIssuedCertificateAction $action): View
    {
        $face = $action->execute($publicId);

        abort_unless($face !== null, 404);

        return view('public.certificates.verify', [
            'certificate' => $face,
        ]);
    }
}
