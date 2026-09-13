<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ResolveCourseContentLanguageAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonPlayerController extends Controller
{
    public function show(Request $request, int $lesson): Response
    {
        abort_unless($request->user() !== null, 403);
        $snapshot = app(ResolvePublishedLessonAction::class)->execute($lesson);
        abort_unless($snapshot !== null, 404, 'This lesson has no published revision.');

        return Inertia::render('Courses/Player/Show', [
            'snapshot' => $snapshot,
            'mediaShowUrl' => '/catalog/media',
            // SPEC §7: "The platform UI language and course content language
            // are separate concepts." Without this the player had no way to
            // know, so every block left at §15.3's default `auto` inherited
            // the page's `lang` — the UI's — and an Arabic course read in a
            // Dhivehi UI was marked up as Dhivehi.
            'courseLanguage' => app(ResolveCourseContentLanguageAction::class)->forLesson($lesson),
        ]);
    }
}
