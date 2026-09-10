<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ServeMaterialFileAction;
use App\Domains\Academics\Models\TeachingMaterialFile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloading a material's file.
 *
 * Deliberately **not** behind the register permissions that guard the rest of
 * the library: a pupil and their guardians reach this too, for materials sent
 * home with the homework. Who may read what is decided in
 * ServeMaterialFileAction, which is the only place that rule exists.
 */
class MaterialFileController extends Controller
{
    public function show(Request $request, TeachingMaterialFile $file): Response
    {
        $media = app(ServeMaterialFileAction::class)->execute($file, $request->user());

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'attachment; filename="'.$media['original_name'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
