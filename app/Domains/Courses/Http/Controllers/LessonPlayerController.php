<?php

namespace App\Domains\Courses\Http\Controllers;

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
        ]);
    }
}
