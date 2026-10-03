<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseForumPost;
use App\Domains\Courses\Models\CourseForumTopic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A course forum's topics, and one topic with its replies (Moodle parity
 * slice M3, STATUS §5oj). Pinned topics first, then the liveliest. A hidden
 * topic or reply is shown to moderators, marked, and to nobody else.
 */
class ListForumAction
{
    /**
     * @return array<string, mixed>
     */
    public function topics(Course $course, string $role): array
    {
        $moderator = $role === ResolveForumAccessAction::MODERATOR;
        $topics = CourseForumTopic::query()
            ->where('course_id', $course->id)
            ->when(! $moderator, fn ($q) => $q->whereNull('hidden_at'))
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        $names = $this->names($topics->pluck('user_id'));
        $teachers = $this->teacherIds($course->id);

        return [
            'course' => ['id' => (int) $course->id, 'title' => (string) $course->title],
            'role' => $role,
            'topics' => $topics->map(fn (CourseForumTopic $topic): array => [
                'id' => (int) $topic->id,
                'title' => (string) $topic->title,
                'author' => $names[(int) $topic->user_id] ?? '',
                'author_is_teacher' => in_array((int) $topic->user_id, $teachers, true),
                'replies' => (int) $topic->replies_count,
                'last_post_at' => $topic->last_post_at?->toIso8601String(),
                'pinned' => (bool) $topic->is_pinned,
                'locked' => (bool) $topic->is_locked,
                'hidden' => $topic->hidden_at !== null,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topic(Course $course, CourseForumTopic $topic, string $role): array
    {
        $moderator = $role === ResolveForumAccessAction::MODERATOR;
        abort_if($topic->hidden_at !== null && ! $moderator, 404);

        $posts = CourseForumPost::query()
            ->where('topic_id', $topic->id)
            ->when(! $moderator, fn ($q) => $q->whereNull('hidden_at'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $names = $this->names($posts->pluck('user_id')->push($topic->user_id));
        $teachers = $this->teacherIds($course->id);
        $person = fn (?int $userId): array => [
            'author' => $names[(int) $userId] ?? '',
            'author_is_teacher' => in_array((int) $userId, $teachers, true),
        ];

        return [
            'course' => ['id' => (int) $course->id, 'title' => (string) $course->title],
            'role' => $role,
            'topic' => [
                'id' => (int) $topic->id,
                'title' => (string) $topic->title,
                'body' => (string) $topic->body,
                'created_at' => $topic->created_at?->toIso8601String(),
                'pinned' => (bool) $topic->is_pinned,
                'locked' => (bool) $topic->is_locked,
                'hidden' => $topic->hidden_at !== null,
            ] + $person($topic->user_id),
            'posts' => $posts->map(fn (CourseForumPost $post): array => [
                'id' => (int) $post->id,
                'body' => (string) $post->body,
                'created_at' => $post->created_at?->toIso8601String(),
                'hidden' => $post->hidden_at !== null,
            ] + $person($post->user_id))->values()->all(),
            'can_reply' => $moderator || ! $topic->is_locked,
        ];
    }

    /**
     * The people who teach the course: their posts are marked, and they hear
     * of new topics.
     *
     * @return list<int>
     */
    public function teacherIds(int $courseId): array
    {
        return DB::table('course_instructor')
            ->join('instructors', 'instructors.id', '=', 'course_instructor.instructor_id')
            ->where('course_instructor.course_id', $courseId)
            ->whereNotNull('instructors.user_id')
            ->pluck('instructors.user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return array<int, string>
     */
    private function names(Collection $ids): array
    {
        return DB::table('users')
            ->whereIn('id', $ids->filter()->unique()->values()->all() ?: [0])
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }
}
