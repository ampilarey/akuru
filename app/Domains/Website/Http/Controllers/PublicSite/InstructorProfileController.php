<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\HR\Actions\ReadPublicInstructorProfileAction;
use App\Domains\Website\Actions\ListResearchPostsAction;
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
            'posts' => app(ListResearchPostsAction::class)->execute([
                'instructor_id' => $instructor['id'],
            ], true),
        ]);
    }
}
