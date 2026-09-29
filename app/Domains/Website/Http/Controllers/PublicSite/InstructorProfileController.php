<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\HR\Actions\ReadPublicInstructorProfileAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Http\Controllers\Controller;

class InstructorProfileController extends Controller
{
    public function show(string $slug)
    {
        $instructor = app(ReadPublicInstructorProfileAction::class)->execute(null, $slug, true);
        if ($instructor === null) {
            abort(404);
        }

        return view('public.instructors.show', [
            'instructor' => $instructor,
            // R2: what the teacher wrote is in the Digital Library now.
            'works' => app(ListLibraryItemsAction::class)->execute(['instructor' => $instructor['id']]),
        ]);
    }
}
