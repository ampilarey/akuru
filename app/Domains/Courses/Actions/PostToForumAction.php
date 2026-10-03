<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseForumPost;
use App\Domains\Courses\Models\CourseForumTopic;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Start a topic or reply in a course forum (Moodle parity slice M3, STATUS
 * §5oj), and tell the people it concerns:
 *
 * - a new topic tells the course's teachers;
 * - a reply tells the topic's starter and everyone who has replied before.
 *
 * Never the writer themselves. A locked topic takes replies from moderators
 * only; a hidden one takes none.
 */
class PostToForumAction
{
    /**
     * @param  array{title: string, body: string}  $data
     */
    public function startTopic(Course $course, int $userId, array $data): CourseForumTopic
    {
        $topic = CourseForumTopic::query()->create([
            'course_id' => $course->id,
            'academic_year_id' => $this->yearId(),
            'user_id' => $userId,
            'title' => Str::limit(trim($data['title']), 200, ''),
            'body' => trim($data['body']),
            'last_post_at' => now(),
        ]);

        $recipients = array_diff(app(ListForumAction::class)->teacherIds((int) $course->id), [$userId]);
        $this->notify($recipients, __('forum.notice_topic_title', ['course' => $course->title]), (string) $topic->title, $course, $topic);

        return $topic;
    }

    public function reply(Course $course, CourseForumTopic $topic, int $userId, string $body, string $role): CourseForumPost
    {
        if ($topic->hidden_at !== null || ($topic->is_locked && $role !== ResolveForumAccessAction::MODERATOR)) {
            throw ValidationException::withMessages(['body' => __('forum.locked_error')]);
        }

        $post = DB::transaction(function () use ($topic, $userId, $body): CourseForumPost {
            $post = CourseForumPost::query()->create([
                'topic_id' => $topic->id,
                'academic_year_id' => $this->yearId(),
                'user_id' => $userId,
                'body' => trim($body),
            ]);
            $topic->forceFill(['last_post_at' => now()])->save();
            $topic->increment('replies_count');

            return $post;
        });

        $earlier = CourseForumPost::query()->where('topic_id', $topic->id)->where('id', '<', $post->id)->pluck('user_id')->all();
        $recipients = array_diff(array_unique(array_map('intval', array_filter([$topic->user_id, ...$earlier]))), [$userId]);
        $this->notify($recipients, __('forum.notice_reply_title', ['topic' => $topic->title]), Str::limit((string) $post->body, 140), $course, $topic);

        return $post;
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function notify(array $userIds, string $title, string $message, Course $course, CourseForumTopic $topic): void
    {
        $href = route('courses.forum.topic', ['course' => $course->id, 'topic' => $topic->id], false);
        foreach (array_slice(array_values($userIds), 0, 200) as $userId) {
            try {
                app(SendUserNotificationAction::class)->execute((int) $userId, $title, $message, ['category' => 'courses', 'href' => $href]);
            } catch (\Throwable $e) {
                // A notice that cannot be written never loses the post.
                Log::warning('Forum notice failed', ['user_id' => $userId, 'topic_id' => $topic->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function yearId(): ?int
    {
        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        return isset($year['id']) ? (int) $year['id'] : null;
    }
}
