<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseForumPost;
use App\Domains\Courses\Models\CourseForumTopic;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use App\Support\Authorization\RoleGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Moodle parity slice M3 (STATUS §5oj). The owner, 2026-10-03, on Moodle's
 * course-building tools: "Yes build". A forum on every course, for its
 * learners and its teachers; the teachers moderate.
 */
uses(RefreshDatabase::class);

function forumCourse(string $title = 'Seerah'): Course
{
    $dean = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => $title,
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $dean->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

function forumLearner(Course $course, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    makeStudent(['user_id' => $user->id, 'first_name' => $name]);
    app(EnrollSelfLearningAction::class)->execute($user->id, (int) $course->id, null);

    return $user;
}

function forumTeacher(Course $course): User
{
    $sets = RoleGrants::matrix();
    foreach ($sets['teacher'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $role = Role::findOrCreate('teacher', 'web');
    $role->syncPermissions($sets['teacher']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $teacher = User::factory()->create(['name' => 'Ustaadh Ali']);
    $teacher->assignRole($role);
    $course->instructors()->sync([Instructor::query()->create(['name' => 'Ustaadh Ali', 'user_id' => $teacher->id])->id]);

    return $teacher->fresh();
}

beforeEach(fn () => $this->withoutLocalizationMiddleware());

it('lets a learner start a topic, tells the teacher, and the replies reach whoever is in the thread', function () {
    $course = forumCourse();
    $aisha = forumLearner($course, 'Aisha');
    $hassan = forumLearner($course, 'Hassan');
    $teacher = forumTeacher($course);

    // The learner finds the forum from the course page.
    $this->actingAs($aisha)->get(route('learn.courses.show', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('forum_href', "/learn/courses/{$course->id}/forum"));
    $this->actingAs($aisha)->get(route('courses.forum.index', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Forum/Index')->where('role', 'participant')->has('topics', 0)->where('t.new_topic', 'Start a topic'));

    $this->actingAs($aisha)->post(route('courses.forum.store', $course->id), ['title' => 'Badr question', 'body' => 'Why Ramadan?'])
        ->assertSessionHasNoErrors();
    $topic = CourseForumTopic::query()->sole();
    expect($topic->user_id)->toBe($aisha->id)->and($topic->course_id)->toBe($course->id)
        ->and(UserNotification::query()->where('user_id', $teacher->id)->where('category', 'courses')->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $aisha->id)->count())->toBe(0);

    // The teacher replies: the starter hears of it.
    $this->actingAs($teacher)->post(route('courses.forum.reply', [$course->id, $topic->id]), ['body' => 'Good question — read chapter 3.'])
        ->assertSessionHasNoErrors();
    expect(UserNotification::query()->where('user_id', $aisha->id)->where('category', 'courses')->count())->toBe(1);

    // Another learner replies: the starter and the teacher hear, not the writer.
    $this->actingAs($hassan)->post(route('courses.forum.reply', [$course->id, $topic->id]), ['body' => 'Thank you.'])
        ->assertSessionHasNoErrors();
    expect(UserNotification::query()->where('user_id', $aisha->id)->count())->toBe(2)
        ->and(UserNotification::query()->where('user_id', $teacher->id)->count())->toBe(2)
        ->and(UserNotification::query()->where('user_id', $hassan->id)->count())->toBe(0)
        ->and($topic->fresh()->replies_count)->toBe(2);

    $this->actingAs($hassan)->get(route('courses.forum.topic', [$course->id, $topic->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Forum/Topic')
            ->where('topic.author', 'Aisha')
            ->has('posts', 2)
            ->where('posts.0.author', 'Ustaadh Ali')
            ->where('posts.0.author_is_teacher', true)
            ->where('posts.1.author_is_teacher', false)
            ->where('can_reply', true));
});

it('lets the teacher pin, lock and hide, and the learners see only what is shown', function () {
    $course = forumCourse();
    $aisha = forumLearner($course, 'Aisha');
    $teacher = forumTeacher($course);
    $this->actingAs($aisha)->post(route('courses.forum.store', $course->id), ['title' => 'First', 'body' => 'One.']);
    $this->actingAs($aisha)->post(route('courses.forum.store', $course->id), ['title' => 'Second', 'body' => 'Two.']);
    [$first, $second] = CourseForumTopic::query()->orderBy('id')->get()->all();

    // A learner cannot moderate.
    $this->actingAs($aisha)->post(route('courses.forum.moderate', [$course->id, $first->id]), ['action' => 'pin'])->assertForbidden();

    // Pinned comes first.
    $this->actingAs($teacher)->post(route('courses.forum.moderate', [$course->id, $first->id]), ['action' => 'pin'])->assertRedirect();
    $this->actingAs($aisha)->get(route('courses.forum.index', $course->id))
        ->assertInertia(fn (Assert $page) => $page->where('topics.0.title', 'First')->where('topics.0.pinned', true));

    // Locked: the learner's reply is refused, the teacher's is not.
    $this->actingAs($teacher)->post(route('courses.forum.moderate', [$course->id, $first->id]), ['action' => 'lock']);
    $this->actingAs($aisha)->post(route('courses.forum.reply', [$course->id, $first->id]), ['body' => 'Still here?'])
        ->assertSessionHasErrors('body');
    $this->actingAs($teacher)->post(route('courses.forum.reply', [$course->id, $first->id]), ['body' => 'Closed now.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($aisha)->get(route('courses.forum.topic', [$course->id, $first->id]))
        ->assertInertia(fn (Assert $page) => $page->where('can_reply', false)->where('topic.locked', true));

    // A hidden reply is gone for the learner, marked for the teacher, and comes back.
    $post = CourseForumPost::query()->sole();
    $this->actingAs($teacher)->post(route('courses.forum.posts.moderate', [$course->id, $post->id]), ['action' => 'hide']);
    $this->actingAs($aisha)->get(route('courses.forum.topic', [$course->id, $first->id]))
        ->assertInertia(fn (Assert $page) => $page->has('posts', 0));
    $this->actingAs($teacher)->get(route('courses.forum.topic', [$course->id, $first->id]))
        ->assertInertia(fn (Assert $page) => $page->where('role', 'moderator')->where('posts.0.hidden', true));
    expect($first->fresh()->replies_count)->toBe(0);
    $this->actingAs($teacher)->post(route('courses.forum.posts.moderate', [$course->id, $post->id]), ['action' => 'show']);
    expect($first->fresh()->replies_count)->toBe(1);

    // A hidden topic: not listed for the learner, and not found.
    $this->actingAs($teacher)->post(route('courses.forum.moderate', [$course->id, $second->id]), ['action' => 'hide']);
    $this->actingAs($aisha)->get(route('courses.forum.index', $course->id))
        ->assertInertia(fn (Assert $page) => $page->has('topics', 1));
    $this->actingAs($aisha)->get(route('courses.forum.topic', [$course->id, $second->id]))->assertNotFound();
    $this->actingAs($teacher)->get(route('courses.forum.index', $course->id))
        ->assertInertia(fn (Assert $page) => $page->has('topics', 2)->where('topics.1.hidden', true));
});

it('keeps the forum to the course: nobody outside it reads or writes', function () {
    $course = forumCourse();
    $other = forumCourse('Fiqh');
    $outsider = forumLearner($other, 'Outsider');
    $stranger = User::factory()->create();
    forumLearner($course, 'Aisha');

    foreach ([$outsider, $stranger] as $user) {
        $this->actingAs($user)->get(route('courses.forum.index', $course->id))->assertForbidden();
        $this->actingAs($user)->post(route('courses.forum.store', $course->id), ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }
    $this->actingAs($outsider)->get(route('learn.courses.show', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('forum_href', null));

    // A topic of another course is not reachable through this one.
    $this->actingAs($outsider)->post(route('courses.forum.store', $other->id), ['title' => 'Mine', 'body' => 'Here.']);
    $foreign = CourseForumTopic::query()->sole();
    $this->actingAs(actingPeopleAdmin(['courses.manage']))->get(route('courses.forum.topic', [$course->id, $foreign->id]))->assertNotFound();

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(trans('forum.intro', [], $locale))->not->toBe('forum.intro')
            ->and(trans('learn.forum_open', [], $locale))->not->toBe('learn.forum_open');
    }
});
