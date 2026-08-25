<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ServeCatalogMediaAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LearnMediaController extends Controller
{
    public function show(Request $request, int $media): Response
    {
        $file = app(ServeCatalogMediaAction::class)->execute($media, $request->user());

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['original_name'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
