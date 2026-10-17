<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryComment;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL5 (STATUS §5sl): comments on blog posts — signed-in readers,
 * the owner's replies, every one checked by the office before it shows, a
 * report taking one off the page, and none on an under-13's blog.
 */
uses(RefreshDatabase::class);

/** A post on a blog — a full writer's, or a student's born on the given date. */
function bl5Post(?string $studentBorn = null): array
{
    if ($studentBorn !== null) {
        $student = makeStudent(['date_of_birth' => $studentBorn]);
        $owner = User::query()->findOrFail($student->user_id);
    } else {
        $owner = User::factory()->create();
    }
    $profile = WriterProfile::query()->create(['user_id' => $owner->id, 'kind' => $studentBorn !== null ? 'student' : 'writer', 'display_name' => 'Starling', 'slug' => 'starling-'.$owner->id, 'status' => 'active', 'approved_at' => now()]);
    $post = LibraryItem::query()->create(['title' => 'The garden begins', 'slug' => 'garden-'.$owner->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'writer_id' => $profile->id, 'body' => '<p>Beans.</p>']);

    return [$owner, $profile, $post];
}

it('shows a comment only once the office publishes it, and the owner\'s reply only once it is checked too', function () {
    [$owner, , $post] = bl5Post();
    $reader = User::factory()->create(['name' => 'Mariyam Ahmed Didi']);
    $web = test()->withoutLocalizationMiddleware();

    $web->get("/library/{$post->slug}")->assertOk()->assertSee('data-testid="comments-sign-in"', false);
    $web->post("/library/posts/{$post->id}/comments", ['body' => 'Hello'])->assertRedirect(route('login'));

    $web->actingAs($reader)->post("/library/posts/{$post->id}/comments", ['body' => "Lovely <b>beans</b>!\nWhich kind?"])->assertSessionHas('success', __('blog.flash_comment_sent'));
    $comment = LibraryComment::query()->sole();
    expect($comment->status)->toBe('pending')->and($comment->body)->toBe("Lovely beans!\nWhich kind?");
    $web->get("/library/{$post->slug}")->assertDontSee('Lovely beans');

    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get('/admin/library/comments')->assertInertia(fn (Assert $p) => $p->component('Library/BlogComments')
        ->where('comments.0.id', $comment->id)->where('comments.0.commenter', 'Mariyam Ahmed Didi'));
    $web->actingAs($office)->post("/admin/library/comments/{$comment->id}/decide", ['decision' => 'publish'])->assertSessionHas('success');
    auth()->logout();
    // Readers see the first name and an initial, never the full name.
    $web->get("/library/{$post->slug}")->assertSee('Lovely beans!')->assertSee('Mariyam D.')->assertDontSee('Ahmed Didi');

    // Only the owner replies; the reply waits too.
    $web->actingAs($reader)->post("/library/posts/{$post->id}/comments", ['body' => 'Me too', 'parent_id' => $comment->id])->assertSessionHasErrors('comment');
    $web->actingAs($owner)->post("/library/posts/{$post->id}/comments", ['body' => 'Broad beans!', 'parent_id' => $comment->id])->assertSessionHas('success');
    $reply = LibraryComment::query()->whereNotNull('parent_id')->sole();
    $web->get("/library/{$post->slug}")->assertDontSee('Broad beans!');
    $web->actingAs($office)->post("/admin/library/comments/{$reply->id}/decide", ['decision' => 'publish']);
    auth()->logout();
    $web->get("/library/{$post->slug}")->assertSeeInOrder(['Lovely beans!', 'Starling', __('blog.comment_owner_reply'), 'Broad beans!']);
});

it('takes a reported comment off the page until the office decides, and needs a note to refuse', function () {
    [, , $post] = bl5Post();
    $reader = User::factory()->create();
    $reporter = User::factory()->create();
    $office = actingSystemAdmin(['library.manage']);
    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($reader)->post("/library/posts/{$post->id}/comments", ['body' => 'Something unkind']);
    $comment = LibraryComment::query()->sole();
    $web->actingAs($office)->post("/admin/library/comments/{$comment->id}/decide", ['decision' => 'publish']);

    auth()->logout();
    $web->post("/library/comments/{$comment->id}/report")->assertRedirect(route('login'));
    $web->actingAs($reporter)->post("/library/comments/{$comment->id}/report")->assertSessionHas('success', __('blog.flash_comment_reported'));
    expect($comment->fresh()->status)->toBe('reported')->and($comment->fresh()->report_count)->toBe(1);
    auth()->logout();
    $web->get("/library/{$post->slug}")->assertDontSee('Something unkind');
    $web->actingAs($office)->get('/admin/library/comments')->assertInertia(fn (Assert $p) => $p->where('comments.0.status', 'reported'));

    $web->actingAs($office)->post("/admin/library/comments/{$comment->id}/decide", ['decision' => 'refuse'])->assertSessionHasErrors('note');
    $web->actingAs($office)->post("/admin/library/comments/{$comment->id}/decide", ['decision' => 'refuse', 'note' => 'Please be kind.'])->assertSessionHas('success');
    expect($comment->fresh()->status)->toBe('refused');
    $csv = $web->actingAs($office)->get('/admin/library/comments/export?status=refused')->assertOk()->streamedContent();
    expect($csv)->toContain('Something unkind')->toContain('Please be kind.');
    $web->actingAs($reader)->get('/admin/library/comments')->assertForbidden();
});

it('takes no comments on an under-13\'s blog, nor where the writer switched them off', function () {
    [$young, , $post] = bl5Post(now()->subYears(11)->toDateString());
    $reader = User::factory()->create();
    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($reader)->get("/library/{$post->slug}")->assertSee('data-testid="comments-closed"', false)->assertDontSee('data-testid="comment-form"', false);
    $web->actingAs($reader)->post("/library/posts/{$post->id}/comments", ['body' => 'Hi'])->assertSessionHasErrors('comment');
    // Their own switch cannot open them.
    $web->actingAs($young)->post('/my-blog/comments', ['enabled' => true]);
    $web->actingAs($reader)->post("/library/posts/{$post->id}/comments", ['body' => 'Hi'])->assertSessionHasErrors('comment');
    $web->actingAs($young)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('blog.under_13', true));

    [$teen, , $teenPost] = bl5Post(now()->subYears(15)->toDateString());
    $web->actingAs($reader)->post("/library/posts/{$teenPost->id}/comments", ['body' => 'Hi'])->assertSessionHas('success');
    $web->actingAs($teen)->post('/my-blog/comments', ['enabled' => false])->assertSessionHas('success', __('blog.flash_comments_off'));
    $web->actingAs($reader)->post("/library/posts/{$teenPost->id}/comments", ['body' => 'Again'])->assertSessionHasErrors('comment');
    // The writer reads the comments on My blog; the office decides.
    $web->actingAs($teen)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('blog.comments.0.body', 'Hi')->where('blog.comments_enabled', false));
});

it('names the comments list and its words in three languages', function () {
    expect(\App\Support\Navigation\NavigationMap::hrefs())->toContain('/admin/library/comments');
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['blog.comments_heading', 'blog.comment_send', 'blog.comments_closed_young', 'nav.blog_comments'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
    expect(__('blog.comments_heading', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('blog.comments_heading', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
