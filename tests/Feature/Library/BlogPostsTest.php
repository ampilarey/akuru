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
 * BLOG_PLAN BL2a (STATUS §5sf): blog posts are written on *My blog* and
 * checked before anyone can read them — a student's by the class teacher,
 * then the office; a full writer's by the office.
 */
uses(RefreshDatabase::class);

/** A student with an approved student blog, on a class whose class teacher is returned too (or none). */
function bl2Student(bool $withTeacher = true): array
{
    $student = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Rasheed', 'date_of_birth' => '2013-05-01']);
    $teacher = $withTeacher ? User::factory()->create() : null;
    if ($withTeacher) {
        $class = makeClass(makeYear());
        DB::table('classes')->where('id', $class->id)->update(['class_teacher_id' => $teacher->id]);
        DB::table('students')->where('id', $student->id)->update(['class_id' => $class->id]);
    }
    $user = User::query()->findOrFail($student->user_id);
    WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now()]);
    $user->assignRole('writer');

    return [$user, $teacher];
}

function bl2Write(User $user, array $data = [], ?int $post = null): \Illuminate\Testing\TestResponse
{
    return test()->withoutLocalizationMiddleware()->actingAs($user)->post($post ? "/my-blog/posts/{$post}" : '/my-blog/posts', $data + [
        'title' => 'My first week at school', 'body' => '<p>We planted a garden.</p>', 'language' => 'en', 'own_work' => true,
    ]);
}

it('takes a student\'s post to the class teacher, then the office, and publishes it only then', function () {
    [$child, $teacher] = bl2Student();
    $web = test()->withoutLocalizationMiddleware();

    bl2Write($child, ['new_series' => 'Garden diary', 'pinned' => true])->assertRedirect(route('my-blog'))->assertSessionHas('success', __('blog.flash_post_saved'));
    $post = LibraryItem::query()->sole();
    expect($post->content_type->value)->toBe('post')->and($post->access_type->value)->toBe('free_public')->and($post->status->value)->toBe('draft')
        ->and($post->series_position)->toBe(1)->and($post->pinned)->toBeTrue();
    $web->get("/library/{$post->slug}")->assertNotFound();

    $web->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success', __('blog.flash_post_to_teacher'));
    $post->refresh();
    expect($post->status->value)->toBe('submitted')->and($post->review_stage)->toBe('teacher')->and($post->teacher_user_id)->toBe($teacher->id);
    expect(DB::table('user_notifications')->where('user_id', $teacher->id)->pluck('data')->map(fn ($d) => json_decode((string) $d, true)['href'] ?? null)->all())->toContain('/teach/blog-posts');

    // The office neither sees it nor can publish it while the teacher has it.
    expect(collect(app(ListWriterQueuesAction::class)->execute()['submissions'])->pluck('id')->all())->not->toContain($post->id);
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/review", ['decision' => 'approved'])->assertSessionHasErrors('item');
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/publish", ['publish' => true])->assertSessionHasErrors('item');
    expect($post->fresh()->status->value)->toBe('submitted');

    // The teacher reads it, with the student's real name, and approves.
    $web->actingAs($teacher)->get('/teach/blog-posts')->assertInertia(fn (Assert $p) => $p->component('Library/TeacherBlogPosts')
        ->where('posts.0.id', $post->id)->where('posts.0.pen_name', 'Starling')->where('posts.0.student_name', 'Aisha Rasheed')->where('posts.0.series', 'Garden diary'));
    $web->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'approved'])->assertSessionHas('success', __('blog.flash_teacher_approved'));
    expect($post->fresh()->review_stage)->toBe('office')->and($post->fresh()->status->value)->toBe('submitted');
    $web->get("/library/{$post->slug}")->assertNotFound();

    // Now the office has it, and publishes.
    expect(collect(app(ListWriterQueuesAction::class)->execute()['submissions'])->pluck('id')->all())->toContain($post->id);
    $web->actingAs($office)->post("/admin/library/items/{$post->id}/review", ['decision' => 'approved'])->assertSessionHas('success');
    expect($post->fresh()->status->value)->toBe('published');
    expect(LibraryItemReview::query()->where('library_item_id', $post->id)->orderBy('id')->pluck('decision')->all())->toBe(['submitted', 'teacher_approved', 'approved']);

    // Readable at its address; kept off the book shelf; on My blog as published.
    auth()->logout();
    $web->get("/library/{$post->slug}")->assertOk();
    $web->get('/library')->assertDontSee('My first week at school');
    $web->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('posts.0.status', 'published')->where('series.0.title', 'Garden diary'));
});

it('gives a post titled in Thaana an address of its own, and keeps it through an edit', function () {
    [$child] = bl2Student(withTeacher: false);
    bl2Write($child, ['title' => 'ބަގީޗާ ފެށުން', 'language' => 'dv']);
    $post = LibraryItem::query()->sole();
    expect($post->slug)->toStartWith('post-')->and(strlen($post->slug))->toBe(13);
    bl2Write($child, ['title' => 'ބަގީޗާ ފެށުން', 'body' => '<p>ހެލޯ</p>'], $post->id);
    expect($post->fresh()->slug)->toBe($post->slug);
});

it('lets only the post\'s teacher decide, needs a note to send back, and lets the student fix and resend', function () {
    [$child, $teacher] = bl2Student();
    bl2Write($child);
    $post = LibraryItem::query()->sole();
    test()->withoutLocalizationMiddleware()->actingAs($child)->post("/my-blog/posts/{$post->id}/submit");

    $other = User::factory()->create();
    test()->withoutLocalizationMiddleware()->actingAs($other)->get('/teach/blog-posts')->assertInertia(fn (Assert $p) => $p->where('posts', []));
    test()->withoutLocalizationMiddleware()->actingAs($other)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'approved'])->assertSessionHasErrors('post');

    // Waiting, it is out of the student's hands.
    bl2Write($child, ['title' => 'Changed while waiting'], $post->id)->assertSessionHasErrors('post');

    test()->withoutLocalizationMiddleware()->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'changes_requested'])->assertSessionHasErrors('comment');
    test()->withoutLocalizationMiddleware()->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'changes_requested', 'comment' => 'Add what you planted.'])
        ->assertSessionHas('success', __('blog.flash_teacher_changes_requested'));
    expect($post->fresh()->status->value)->toBe('changes_requested');
    test()->withoutLocalizationMiddleware()->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('posts.0.note', 'Add what you planted.')->where('posts.0.editable', true));

    bl2Write($child, ['body' => '<p>We planted beans.</p>'], $post->id)->assertSessionHas('success');
    test()->withoutLocalizationMiddleware()->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success', __('blog.flash_post_to_teacher'));
    expect($post->fresh()->review_stage)->toBe('teacher');

    test()->withoutLocalizationMiddleware()->actingAs($teacher)->post("/teach/blog-posts/{$post->id}/decide", ['decision' => 'rejected', 'comment' => 'Not for the blog.']);
    expect($post->fresh()->status->value)->toBe('rejected');
});

it('sends a post straight to the office with no class teacher, or with the teacher step off', function () {
    [$learner] = bl2Student(withTeacher: false);
    bl2Write($learner);
    $post = LibraryItem::query()->sole();
    test()->withoutLocalizationMiddleware()->actingAs($learner)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success', __('blog.flash_post_to_office'));
    expect($post->fresh()->review_stage)->toBe('office')->and($post->fresh()->teacher_user_id)->toBeNull();

    config(['library.blog.teacher_review' => false]);
    [$pupil] = bl2Student();
    bl2Write($pupil, ['title' => 'Another']);
    $second = LibraryItem::query()->where('created_by', $pupil->id)->sole();
    test()->withoutLocalizationMiddleware()->actingAs($pupil)->post("/my-blog/posts/{$second->id}/submit");
    expect($second->fresh()->review_stage)->toBe('office');
});

it('needs the own-work tick and a text, and caps a student at three posts in seven days', function () {
    [$child] = bl2Student(withTeacher: false);
    bl2Write($child, ['own_work' => false]);
    $post = LibraryItem::query()->sole();
    test()->withoutLocalizationMiddleware()->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHasErrors('own_work');
    bl2Write($child, ['body' => '<p> </p>'], $post->id);
    test()->withoutLocalizationMiddleware()->actingAs($child)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHasErrors('body');

    foreach (['One', 'Two', 'Three'] as $title) {
        bl2Write($child, ['title' => $title]);
        test()->withoutLocalizationMiddleware()->actingAs($child)->post('/my-blog/posts/'.LibraryItem::query()->where('title', $title)->value('id').'/submit')->assertSessionHas('success');
    }
    bl2Write($child, ['title' => 'Four']);
    test()->withoutLocalizationMiddleware()->actingAs($child)->post('/my-blog/posts/'.LibraryItem::query()->where('title', 'Four')->value('id').'/submit')->assertSessionHasErrors('post');

    // Someone else's series is refused.
    [$other] = bl2Student(withTeacher: false);
    bl2Write($other, ['new_series' => 'Theirs']);
    bl2Write($child, ['title' => 'Five', 'series_id' => DB::table('library_post_series')->where('title', 'Theirs')->value('id')])->assertSessionHasErrors('series_id');
});

it('sends a full writer\'s post to the office, and keeps posts out of the writer portal\'s books', function () {
    $writer = User::factory()->create();
    WriterProfile::query()->create(['user_id' => $writer->id, 'display_name' => 'Adult Writer', 'slug' => 'adult-writer', 'status' => 'active']);
    $writer->assignRole('writer');

    bl2Write($writer, ['title' => 'Notes on reading']);
    $post = LibraryItem::query()->sole();
    test()->withoutLocalizationMiddleware()->actingAs($writer)->post("/my-blog/posts/{$post->id}/submit")->assertSessionHas('success', __('blog.flash_post_to_office'));
    expect($post->fresh()->review_stage)->toBe('office');

    $web = test()->withoutLocalizationMiddleware()->actingAs($writer);
    $web->get('/write')->assertInertia(fn (Assert $p) => $p->where('options.content_types', fn ($types) => ! collect($types)->contains('post'))->where('dashboard.items', []));
    $web->post('/write/items', ['title' => 'A post', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en'])->assertSessionHasErrors('content_type');
    $web->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('state', 'writer')->where('posts.0.title', 'Notes on reading'));
});

it('says Students’ blog posts under Teaching and My blog for writers, in three languages', function () {
    expect(\App\Support\Navigation\NavigationMap::hrefs())->toContain('/teach/blog-posts')
        ->and(array_column(\App\Support\Navigation\NavigationMap::primary()['writer'], 'href'))->toContain('/my-blog');
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['nav.blog_posts', 'blog.new_post', 'blog.teacher_title', 'common.library_type_post'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
    expect(__('blog.new_post', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('blog.new_post', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
