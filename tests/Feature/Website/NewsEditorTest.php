<?php

use App\Domains\Identity\Models\User;
use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Models\Post;
use App\Domains\Website\Models\PostCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * RESEARCH_ARTICLES_PLAN R4 (STATUS §5ks, the owner's decision D3): the
 * office writes the website's news. News stays a website post, so `/news`
 * and the home page read it as before; the body is sanitised on the way in.
 */
function newsOffice(): User
{
    return actingSystemAdmin();
}

function writeNews(User $admin, array $data = []): TestResponse
{
    return test()->withoutLocalizationMiddleware()->actingAs($admin)->post(route('admin.news.store'), $data + [
        'title' => 'Open Day on Saturday',
        'summary' => 'Visit the institute.',
        'body' => '<p onclick="steal()">Doors open at nine.</p><script>alert(1)</script>',
        'is_published' => 1,
    ]);
}

it('writes and publishes news that reaches /news, its page and the home page, sanitised', function () {
    Storage::fake('public');
    $admin = newsOffice();
    $category = PostCategory::query()->create(['name' => 'Events', 'slug' => 'events', 'is_active' => true, 'sort_order' => 1]);

    writeNews($admin, [
        'post_category_id' => $category->id,
        'tags' => 'open day, visits',
        'is_featured' => 1,
        'cover' => UploadedFile::fake()->image('cover.jpg', 800, 450),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $post = Post::query()->where('slug', 'open-day-on-saturday')->sole();
    expect($post->type)->toBe('news')
        ->and($post->body)->not->toContain('onclick')->and($post->body)->not->toContain('<script')
        ->and($post->body)->toContain('Doors open at nine.')
        ->and($post->tags)->toBe(['open day', 'visits'])
        ->and($post->is_featured)->toBeTrue()
        ->and($post->cover_image)->toStartWith('news-covers/')
        ->and($post->author_id)->toBe($admin->id);
    Storage::disk('public')->assertExists($post->cover_image);

    Cache::flush();
    // The list links to the item itself — it used to pass the language code
    // where the slug belongs, so every link led nowhere (found by R4's walk).
    $this->withoutLocalizationMiddleware()->get(route('public.news.index'))->assertOk()->assertSee('Open Day on Saturday')
        ->assertSee('href="'.route('public.news.show', $post->slug).'"', false);
    $this->withoutLocalizationMiddleware()->get(route('public.news.show', $post->slug))->assertOk()->assertSee('Doors open at nine.')->assertDontSee('steal()', false);
    $this->withoutLocalizationMiddleware()->get(route('public.home'))->assertOk()->assertSee('Open Day on Saturday');
});

it('keeps a draft and a scheduled item off the public pages', function () {
    $admin = newsOffice();
    writeNews($admin, ['title' => 'Quiet Draft', 'is_published' => 0])->assertSessionHasNoErrors();
    writeNews($admin, ['title' => 'Next Week', 'published_at' => now()->addWeek()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

    Cache::flush();
    $this->withoutLocalizationMiddleware()->get(route('public.news.index'))->assertDontSee('Quiet Draft')->assertDontSee('Next Week');
    $this->withoutLocalizationMiddleware()->get(route('public.news.show', 'quiet-draft'))->assertNotFound();

    // The office sees all three states, and previews a draft.
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.news.index'))
        ->assertInertia(fn ($page) => $page->component('Website/News')->has('posts', 2)
            ->where('posts.0.state', 'draft'));
    $draft = Post::query()->where('slug', 'quiet-draft')->sole();
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.news.show', $draft->id))
        ->assertInertia(fn ($page) => $page->component('Website/NewsPreview')->where('post.state', 'draft'));
});

it('edits, pins, unpublishes, and refuses an address another post uses', function () {
    $admin = newsOffice();
    writeNews($admin)->assertSessionHasNoErrors();
    $post = Post::query()->sole();

    $this->withoutLocalizationMiddleware()->actingAs($admin)->put(route('admin.news.update', $post->id), [
        'title' => 'Open Day moved to Sunday', 'slug' => $post->slug, 'body' => '<p>Sunday now.</p>', 'is_published' => 1, 'is_pinned' => 1,
    ])->assertSessionHasNoErrors();
    expect($post->fresh()->only(['title', 'is_pinned']))->toBe(['title' => 'Open Day moved to Sunday', 'is_pinned' => true]);

    $this->withoutLocalizationMiddleware()->actingAs($admin)->put(route('admin.news.update', $post->id), [
        'title' => 'Open Day moved to Sunday', 'slug' => $post->slug, 'body' => '<p>Sunday now.</p>', 'is_published' => 0,
    ])->assertSessionHasNoErrors();
    expect($post->fresh()->is_published)->toBeFalse()->and($post->fresh()->published_at)->toBeNull();

    writeNews($admin, ['title' => 'Another', 'slug' => $post->slug])->assertSessionHasErrors('slug');

    // The editor only ever opens news, never another kind of post.
    $article = Post::query()->create(['type' => 'article', 'title' => 'Old', 'slug' => 'old', 'summary' => 's', 'body' => 'b', 'author_id' => $admin->id]);
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.news.edit', $article->id))->assertNotFound();
});

it('exports the list, keeps categories, and is the website office\'s alone', function () {
    $admin = newsOffice();
    writeNews($admin)->assertSessionHasNoErrors();

    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.news.export'))->streamedContent();
    expect($csv)->toContain('Open Day on Saturday')->and($csv)->toContain('live');

    $this->withoutLocalizationMiddleware()->actingAs($admin)->post(route('admin.news.categories.store'), ['name' => 'Announcements'])->assertSessionHasNoErrors();
    $category = PostCategory::query()->where('slug', 'announcements')->sole();
    $this->withoutLocalizationMiddleware()->actingAs($admin)->put(route('admin.news.categories.update', $category->id), ['name' => 'Announcements', 'is_active' => 0])->assertSessionHasNoErrors();
    expect($category->fresh()->is_active)->toBeFalse();
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.news.categories'))
        ->assertInertia(fn ($page) => $page->component('Website/NewsCategories')->has('categories', 1));

    $stranger = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($stranger)->get(route('admin.news.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($stranger)->post(route('admin.news.store'), ['title' => 'x', 'body' => 'y'])->assertForbidden();
});

it('renders the JSON-LD on a news page and an event page, now that @context is a Blade word', function () {
    // Laravel 12 made `@context` a directive; the bare JSON-LD key opened a
    // block that never closed, and both pages answered 500 whenever there
    // was anything to show (found by this slice's first test).
    $admin = newsOffice();
    writeNews($admin)->assertSessionHasNoErrors();
    $event = app(SaveEventAction::class)->execute([
        'title' => 'Quran Evening', 'location' => 'Malé', 'status' => 'published', 'is_public' => true, 'registration_type' => 'none',
        'start_date' => now()->addDays(3)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(3)->addHours(2)->format('Y-m-d H:i:s'),
    ]);

    $this->withoutLocalizationMiddleware()->get(route('public.news.show', 'open-day-on-saturday'))
        ->assertOk()->assertSee('"@context": "https://schema.org"', false);
    $this->withoutLocalizationMiddleware()->get(route('public.events.show', $event->id))
        ->assertOk()->assertSee('"@context": "https://schema.org"', false)->assertSee('Quran Evening');
});
