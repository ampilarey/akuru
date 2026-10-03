<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListForumAction;
use App\Domains\Courses\Actions\ModerateForumAction;
use App\Domains\Courses\Actions\PostToForumAction;
use App\Domains\Courses\Actions\ResolveForumAccessAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseForumPost;
use App\Domains\Courses\Models\CourseForumTopic;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A course's discussion forum (Moodle parity slice M3, STATUS §5oj): its
 * learners and teachers, nobody else. ResolveForumAccessAction says who.
 */
class CourseForumController extends Controller
{
    public function index(Request $request, int $course): Response
    {
        [$model, $role] = $this->authorizeForum($request, $course);

        return Inertia::render('Courses/Forum/Index', app(ListForumAction::class)->topics($model, $role) + ['t' => Phrases::once('forum')]);
    }

    public function show(Request $request, int $course, int $topic): Response
    {
        [$model, $role] = $this->authorizeForum($request, $course);

        return Inertia::render('Courses/Forum/Topic', app(ListForumAction::class)->topic($model, $this->topicOf($model, $topic), $role) + ['t' => Phrases::once('forum')]);
    }

    public function store(Request $request, int $course): RedirectResponse
    {
        [$model] = $this->authorizeForum($request, $course);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $topic = app(PostToForumAction::class)->startTopic($model, (int) $request->user()->id, $data);

        return redirect()->route('courses.forum.topic', [$course, $topic->id])->with('success', __('forum.topic_started'));
    }

    public function reply(Request $request, int $course, int $topic): RedirectResponse
    {
        [$model, $role] = $this->authorizeForum($request, $course);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        app(PostToForumAction::class)->reply($model, $this->topicOf($model, $topic), (int) $request->user()->id, $data['body'], $role);

        return redirect()->route('courses.forum.topic', [$course, $topic])->with('success', __('forum.replied'));
    }

    public function moderateTopic(Request $request, int $course, int $topic): RedirectResponse
    {
        [$model] = $this->authorizeForum($request, $course, moderator: true);
        $data = $request->validate(['action' => ['required', Rule::in(ModerateForumAction::TOPIC_ACTIONS)]]);
        app(ModerateForumAction::class)->topic($this->topicOf($model, $topic), $data['action'], (int) $request->user()->id);

        return back()->with('success', __('forum.moderated'));
    }

    public function moderatePost(Request $request, int $course, int $post): RedirectResponse
    {
        [$model] = $this->authorizeForum($request, $course, moderator: true);
        $data = $request->validate(['action' => ['required', Rule::in(ModerateForumAction::POST_ACTIONS)]]);
        $row = CourseForumPost::query()->whereKey($post)->whereHas('topic', fn ($q) => $q->where('course_id', $model->id))->firstOrFail();
        app(ModerateForumAction::class)->post($row, $data['action'], (int) $request->user()->id);

        return back()->with('success', __('forum.moderated'));
    }

    /**
     * @return array{0: Course, 1: string}
     */
    private function authorizeForum(Request $request, int $course, bool $moderator = false): array
    {
        $model = Course::query()->findOrFail($course);
        $role = app(ResolveForumAccessAction::class)->execute((int) $model->id, $request->user());
        abort_if($role === null || ($moderator && $role !== ResolveForumAccessAction::MODERATOR), 403);

        return [$model, $role];
    }

    private function topicOf(Course $course, int $topic): CourseForumTopic
    {
        return CourseForumTopic::query()->where('course_id', $course->id)->findOrFail($topic);
    }
}
