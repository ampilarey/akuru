<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListWriterQueuesAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL2c (STATUS §5sh, D3): an edit to a published post waits beside
 * the live text and is checked as a new post is — the class teacher, then
 * the office for a student's — before readers see it.
 */
uses(RefreshDatabase::class);

/** A published post on a student's blog, the student, and their class teacher (or none). */
function bl2cPublished(bool $withTeacher = true): array
{
    $student = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Rasheed', 'date_of_birth' => '2013-05-01']);
    $teacher = $withTeacher ? User::factory()->create() : null;
    if ($withTeacher) {
        $class = makeClass(makeYear());
        DB::table('classes')->where('id', $class->id)->update(['class_teacher_id' => $teacher->id]);
        DB::table('students')->where('id', $student->id)->update(['class_id' => $class->id]);
    }
    $user = User::query()->findOrFail($student->user_id);
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now()]);
    $user->assignRole('writer');
    $post = LibraryItem::query()->create([
        'title' => 'The garden begins', 'slug' => 'the-garden-begins-'.$user->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en',
        'status' => 'published', 'published_at' => now(), 'writer_id' => $profile->id, 'body' => '<p>We planted beans.</p>', 'declarations' => ['copyright' => true],
    ]);

    return [$user, $teacher, $post];
}

function bl2cEdit(User $user, LibraryItem $post, array $data = []): \Illuminate\Testing\TestResponse
{
    return test()->withoutLocalizationMiddleware()->actingAs($user)->post("/my-blog/posts/{$post->id}", $data + [
        'title' => 'The garden begins (updated)', 'body' => '<p>We planted beans and maize.</p><script>alert(1)</script>', 'own_work' => true,
    ]);
}

it('keeps the live post as it was until the teacher and then the office approve the edit', function () {
    [$child, $teacher, $post] = bl2cPublished();
    $web = test()->withoutLocalizationMiddleware();

    bl2cEdit($child, $post)->assertSessionHas('success');
    $post->refresh();
    expect($post->title)->toBe('The garden begins')->and($post->body)->toBe('<p>We planted beans.</p>')
        ->and($post->pending_title)->toBe('The garden begins (updated)')->and($post->pending_body)->not->toContain('<script')
        ->and($post->status->value)->toBe('published');
    $web->get("/library/{$post->slug}")->assertOk()->assertSee('We planted beans.')->assertDontSee('maize');
    $web->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('posts.0.has_change', true)->where('posts.0.edit_title', 'The garden begins (updated)')->where('posts.0.editable', true));

    $web->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success', __('blog.flash_post_to_teacher'));
    expect($post->fresh()->review_stage)->toBe('teacher')->and($post->fresh()->status->value)->toBe('published');
    // While it is being checked, it cannot be edited again, and the office cannot take it.
    bl2cEdit($child, $post, ['title' => 'Again'])->assertSessionHasErrors('post');
    expect(collect(app(ListWriterQueuesAction::class)->execute()['submissions'])->pluck('id')->all())->not->toContain($post->id);
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/review", ['decision' => 'approved'])->assertSessionHasErrors('item');

    $web->actingAs($teacher)->get('/teach/blog-posts')->assertInertia(fn (Assert $p) => $p->where('posts.0.change', true)->where('posts.0.edit_title', 'The garden begins (updated)'));
    $web->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'approved'])->assertSessionHas('success');
    $web->get("/library/{$post->slug}")->assertDontSee('maize');

    $queue = collect(app(ListWriterQueuesAction::class)->execute()['submissions'])->firstWhere('id', $post->id);
    expect($queue['change'])->toBeTrue()->and($queue['pending_title'])->toBe('The garden begins (updated)');
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/review", ['decision' => 'approved'])->assertSessionHas('success');

    $post->refresh();
    expect($post->title)->toBe('The garden begins (updated)')->and($post->body)->toContain('maize')->and($post->pending_saved_at)->toBeNull()
        ->and($post->review_stage)->toBeNull()->and($post->status->value)->toBe('published');
    auth()->logout();
    $web->get("/library/{$post->slug}")->assertOk()->assertSee('maize');
    expect(LibraryItemReview::query()->where('library_item_id', $post->id)->orderBy('id')->pluck('decision')->all())
        ->toBe(['edit_submitted', 'teacher_edit_approved', 'edit_approved']);
});

it('keeps a sent-back edit for the student to fix, and drops a refused one, the post staying live', function () {
    [$child, $teacher, $post] = bl2cPublished();
    $web = test()->withoutLocalizationMiddleware();
    bl2cEdit($child, $post);
    $web->actingAs($child)->post("/my-blog/posts/{$post->id}/submit");

    $web->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'changes_requested', 'comment' => 'Say which beans.'])->assertSessionHas('success');
    expect($post->fresh()->pending_title)->toBe('The garden begins (updated)')->and($post->fresh()->review_stage)->toBeNull()->and($post->fresh()->status->value)->toBe('published');
    $web->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('posts.0.note', 'Say which beans.')->where('posts.0.editable', true));

    bl2cEdit($child, $post, ['body' => '<p>Broad beans.</p>'])->assertSessionHas('success');
    $web->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success');
    $web->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'rejected', 'comment' => 'Keep it as it was.']);
    $post->refresh();
    expect($post->pending_saved_at)->toBeNull()->and($post->pending_body)->toBeNull()->and($post->body)->toBe('<p>We planted beans.</p>')->and($post->status->value)->toBe('published');
});

it('takes a full writer\'s edit to the office, which may send it back or refuse it', function () {
    [$learner, , $post] = bl2cPublished(withTeacher: false);
    $web = test()->withoutLocalizationMiddleware();
    // Nothing to send before an edit.
    $web->actingAs($learner)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHasErrors('post');

    bl2cEdit($learner, $post);
    $web->actingAs($learner)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success', __('blog.flash_post_to_office'));
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/review", ['decision' => 'changes_requested', 'comment' => 'Shorter title.'])->assertSessionHas('success');
    expect($post->fresh()->pending_title)->not->toBeNull()->and($post->fresh()->title)->toBe('The garden begins');

    bl2cEdit($learner, $post, ['title' => 'Garden']);
    $web->actingAs($learner)->post("/my-blog/posts/{$post->id}/submit");
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/review", ['decision' => 'rejected']);
    expect($post->fresh()->pending_saved_at)->toBeNull()->and($post->fresh()->title)->toBe('The garden begins');
});

it('does not count an edit against the weekly cap, and words it in three languages', function () {
    config(['library.blog.posts_per_week' => 1]);
    [$child, , $post] = bl2cPublished(withTeacher: false);
    LibraryItem::query()->whereKey($post->id)->update(['submitted_at' => now()]);
    bl2cEdit($child, $post);
    test()->withoutLocalizationMiddleware()->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success');

    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['blog.change_help', 'blog.change_status_teacher', 'admin.library_office_post_edit', 'admin.library_office_decision_edit_approved'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
});
