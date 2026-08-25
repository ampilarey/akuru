<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AuthorizeLessonAccessAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Domains\Courses\Actions\StartOrCompleteLessonProgressAction;
use App\Domains\Offerings\Actions\ResolveOfferingPinAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnLessonController extends Controller
{
    public function show(Request $request, int $lesson): Response
    {
        $access = app(AuthorizeLessonAccessAction::class)->execute($lesson, $request->user());
        $pinnedRevision = $access['enrollment']?->course_offering_id
            ? app(ResolveOfferingPinAction::class)->revisionIdForLesson(
                (int) $access['enrollment']->course_offering_id,
                $lesson,
            )
            : null;
        $snapshot = app(ResolvePublishedLessonAction::class)->execute($lesson, $pinnedRevision);
        abort_unless($snapshot !== null, 404, 'This lesson has no published revision.');

        if ($access['enrollment'] !== null) {
            app(StartOrCompleteLessonProgressAction::class)->execute($lesson, $request->user(), 'in_progress');
        }

        return Inertia::render('Courses/Player/Show', [
            'snapshot' => $snapshot,
            'mediaShowUrl' => '/learn/media',
            'canComplete' => $access['enrollment'] !== null,
            'completeUrl' => '/learn/lessons/'.$lesson.'/complete',
        ]);
    }

    public function complete(Request $request, int $lesson): RedirectResponse
    {
        app(StartOrCompleteLessonProgressAction::class)->execute($lesson, $request->user(), 'completed');

        return redirect()->route('learn.lessons.show', $lesson)->with('success', 'Lesson marked complete.');
    }
}
