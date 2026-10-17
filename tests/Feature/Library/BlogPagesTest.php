<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPostSeries;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * BLOG_PLAN BL2b (STATUS §5sg): the blogs' front, the blog on the author
 * page, a post's series, and posts in the search and the sitemap — only ever
 * what the office published.
 */
uses(RefreshDatabase::class);

function bl2bBlog(string $kind = 'student', array $profile = []): WriterProfile
{
    $user = User::factory()->create();

    return WriterProfile::query()->create($profile + [
        'user_id' => $user->id, 'kind' => $kind, 'display_name' => 'Starling', 'blog_title' => 'Notes from Malé',
        'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now(),
    ]);
}

function bl2bPost(WriterProfile $writer, string $title, string $status = 'published', array $extra = []): LibraryItem
{
    static $n = 0;
    $n++;

    return LibraryItem::query()->create($extra + [
        'title' => $title, 'slug' => 'post-'.$n.'-'.$writer->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en',
        'status' => $status, 'published_at' => $status === 'published' ? now()->subMinutes(100 - $n) : null, 'writer_id' => $writer->id,
        'body' => '<p>'.$title.' — what we did this week.</p>',
    ]);
}

it('lists the latest published posts and the blogs on /library/blogs, and nothing unpublished', function () {
    $blog = bl2bBlog();
    bl2bPost($blog, 'The garden begins');
    bl2bPost($blog, 'Waiting for the teacher', 'submitted');
    $dv = bl2bPost(bl2bBlog('writer', ['display_name' => 'Adult Writer', 'blog_title' => null]), 'ދިވެހި ޕޯސްޓް', 'published', ['language' => 'dv']);

    $web = test()->withoutLocalizationMiddleware();
    $web->get('/library/blogs')->assertOk()
        ->assertSee('The garden begins')->assertSee('what we did this week')->assertSee('Notes from Malé')->assertSee(__('public.blogs_student'))
        ->assertSee('ދިވެހި ޕޯސްޓް')->assertDontSee('Waiting for the teacher');
    $web->get('/library/blogs?language=dv')->assertOk()->assertSee('ދިވެހި ޕޯސްޓް')->assertDontSee('The garden begins');
    $web->get("/library/{$dv->slug}")->assertOk()->assertSee(route('public.library.blogs'), false);
});

it('shows the blog on the author page: pinned first, then the latest, the series, and the works apart', function () {
    $blog = bl2bBlog();
    $series = LibraryPostSeries::query()->create(['writer_id' => $blog->id, 'title' => 'Garden diary', 'slug' => 'garden-diary']);
    bl2bPost($blog, 'Part one', 'published', ['series_id' => $series->id, 'series_position' => 1]);
    bl2bPost($blog, 'Pinned thoughts', 'published', ['pinned' => true]);
    bl2bPost($blog, 'Part two draft', 'draft', ['series_id' => $series->id, 'series_position' => 2]);
    LibraryItem::query()->create(['title' => 'A book of theirs', 'slug' => 'a-book-of-theirs', 'content_type' => 'book', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'writer_id' => $blog->id]);

    $page = test()->withoutLocalizationMiddleware()->get("/library/authors/{$blog->slug}")->assertOk();
    $page->assertSee('Notes from Malé')->assertSee(__('public.blogs_pinned'))->assertSee('Garden diary')->assertSee('A book of theirs')->assertDontSee('Part two draft');
    $page->assertSeeInOrder(['Pinned thoughts', 'Part one', __('public.blogs_series'), __('public.Published works'), 'A book of theirs']);
});

it('hides a student\'s photo unless their parent allowed it', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('writers/bl2b.jpg', 'jpeg');
    $photo = \App\Domains\Media\Models\MediaFile::query()->create([
        'disk' => 'public', 'path' => 'writers/bl2b.jpg', 'mime' => 'image/jpeg', 'original_name' => 'bl2b.jpg', 'size' => 8,
        'visibility' => 'public', 'process_status' => 'processed', 'processed_at' => now(),
    ]);
    $hidden = bl2bBlog('student', ['show_photo' => false, 'photo_media_file_id' => $photo->id]);
    $shown = bl2bBlog('student', ['show_photo' => true, 'photo_media_file_id' => $photo->id]);
    $author = app(\App\Domains\Library\Actions\PresentWriterPublicProfileAction::class);
    expect($author->execute($hidden->slug)['photo_url'])->toBeNull()->and($author->execute($hidden->slug)['student'])->toBeTrue()
        ->and($author->execute($shown->slug)['photo_url'])->not->toBeNull();
});

it('shows a post its place in the series, counting only published parts', function () {
    $blog = bl2bBlog();
    $series = LibraryPostSeries::query()->create(['writer_id' => $blog->id, 'title' => 'Garden diary', 'slug' => 'garden-diary']);
    $one = bl2bPost($blog, 'Part one', 'published', ['series_id' => $series->id, 'series_position' => 1]);
    bl2bPost($blog, 'Part two, still being checked', 'submitted', ['series_id' => $series->id, 'series_position' => 2]);
    $three = bl2bPost($blog, 'Part three', 'published', ['series_id' => $series->id, 'series_position' => 3]);

    test()->withoutLocalizationMiddleware()->get("/library/{$three->slug}")->assertOk()
        ->assertSee(__('public.blogs_series_part', ['n' => 2, 'count' => 2, 'series' => 'Garden diary']), false)
        ->assertSee(route('public.library.show', $one->slug), false)->assertDontSee('Part two, still being checked');
    test()->withoutLocalizationMiddleware()->get("/library/{$one->slug}")->assertSee(__('public.blogs_next'))->assertDontSee(__('public.blogs_previous'));
});

it('keeps `blogs` for the blogs\' front, finds posts in the search, and lists the front in the sitemap', function () {
    $item = app(SaveLibraryItemAction::class)->execute(['title' => 'Blogs', 'content_type' => 'book', 'access_type' => 'free_public']);
    expect($item->slug)->not->toBe('blogs')->toStartWith('blogs-');

    $blog = bl2bBlog();
    bl2bPost($blog, 'Coral reefs near Malé');
    $web = test()->withoutLocalizationMiddleware();
    $web->get('/library/blogs')->assertOk()->assertSee('Coral reefs near Malé');
    $web->get('/library')->assertDontSee('Coral reefs near Malé');
    $web->get('/search?q=Coral')->assertOk()->assertSee('Coral reefs near Malé');
    expect(app(\App\Domains\Website\Actions\BuildPublicSitemapAction::class)->execute())->toContain('/library/blogs');
});

it('says Blogs in the Digital Library menu and footer, in three languages', function () {
    test()->withoutLocalizationMiddleware()->get('/library')->assertOk()->assertSee(route('public.library.blogs'), false);
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['site.blogs', 'public.blogs_title', 'public.post', 'public.blogs_series_part'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
    expect(__('public.blogs_title', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('public.blogs_title', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
