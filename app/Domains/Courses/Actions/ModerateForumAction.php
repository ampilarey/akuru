<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseForumPost;
use App\Domains\Courses\Models\CourseForumTopic;
use Illuminate\Validation\ValidationException;

/**
 * A moderator's hand on a course forum (Moodle parity slice M3, STATUS §5oj):
 * pin a topic to the top, lock it against replies, hide a topic or a reply.
 * Hiding is undone by showing; nothing is deleted.
 */
class ModerateForumAction
{
    public const TOPIC_ACTIONS = ['pin', 'unpin', 'lock', 'unlock', 'hide', 'show'];

    public const POST_ACTIONS = ['hide', 'show'];

    public function topic(CourseForumTopic $topic, string $action, int $moderatorId): CourseForumTopic
    {
        $changes = match ($action) {
            'pin' => ['is_pinned' => true],
            'unpin' => ['is_pinned' => false],
            'lock' => ['is_locked' => true],
            'unlock' => ['is_locked' => false],
            'hide' => ['hidden_at' => now(), 'hidden_by' => $moderatorId],
            'show' => ['hidden_at' => null, 'hidden_by' => null],
            default => throw ValidationException::withMessages(['action' => __('forum.error_unknown_action')]),
        };
        $topic->forceFill($changes)->save();

        return $topic;
    }

    public function post(CourseForumPost $post, string $action, int $moderatorId): CourseForumPost
    {
        $changes = match ($action) {
            'hide' => ['hidden_at' => now(), 'hidden_by' => $moderatorId],
            'show' => ['hidden_at' => null, 'hidden_by' => null],
            default => throw ValidationException::withMessages(['action' => __('forum.error_unknown_action')]),
        };
        $post->forceFill($changes)->save();

        // The count on the list is what a learner can read.
        $topic = $post->topic;
        $topic->forceFill(['replies_count' => CourseForumPost::query()->where('topic_id', $topic->id)->whereNull('hidden_at')->count()])->save();

        return $post;
    }
}
