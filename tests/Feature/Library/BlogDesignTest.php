<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL3a (STATUS §5si, D14): a blogger designs their page — theme,
 * accent, heading font, tagline, about, languages, sections — as a draft the
 * office checks before readers see it.
 */
uses(RefreshDatabase::class);

function bl3Blog(): array
{
    $user = User::factory()->create();
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'blog_title' => 'Notes from Malé', 'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now(), 'bio' => 'The old about.']);
    $user->assignRole('writer');
    LibraryItem::query()->create(['title' => 'The garden begins', 'slug' => 'garden-'.$user->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'writer_id' => $profile->id, 'body' => '<p>Beans.</p>']);

    return [$user, $profile];
}

function bl3Design(array $data = []): array
{
    return $data + [
        'theme' => 'night', 'accent' => '#F2C778', 'font' => 'Lora', 'tagline' => 'Stories from the islands',
        'about' => "I write about <b>gardens</b>.\nAnd the sea.", 'languages' => ['dv', 'en'],
        'sections' => [['key' => 'about', 'on' => true], ['key' => 'latest', 'on' => true], ['key' => 'series', 'on' => false]],
    ];
}

it('keeps the live page as it was until the office approves the whole set of changes', function () {
    [$user, $profile] = bl3Blog();
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($user)->get('/my-blog/design')->assertInertia(fn (Assert $p) => $p->component('Library/BlogDesign')
        ->where('state', 'live')->where('themes', fn ($themes) => collect($themes)->pluck('key')->all() === ['akuru', 'ocean', 'forest', 'sand', 'night'])
        ->where('live.theme', 'akuru')->where('posts.0.title', 'The garden begins'));

    $web->actingAs($user)->post('/my-blog/design', bl3Design())->assertRedirect(route('my-blog.design'))->assertSessionHas('success', __('blog.flash_page_saved'));
    $profile->refresh();
    expect($profile->pending_page['theme'])->toBe('night')->and($profile->pending_page['about'])->toBe("I write about gardens.\nAnd the sea.")
        ->and($profile->theme_key)->toBeNull()->and($profile->bio)->toBe('The old about.');
    $web->get("/library/authors/{$profile->slug}")->assertOk()->assertSee('data-blog-theme="akuru"', false)->assertDontSee('Stories from the islands');

    $web->actingAs($user)->post('/my-blog/design/submit')->assertSessionHas('success', __('blog.flash_page_sent'));
    // Sent, it cannot be changed until it is decided.
    $web->actingAs($user)->post('/my-blog/design', bl3Design(['tagline' => 'Changed']))->assertSessionHasErrors('page');
    $web->actingAs($user)->get('/my-blog/design')->assertInertia(fn (Assert $p) => $p->where('state', 'waiting')->where('draft.tagline', 'Stories from the islands'));

    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get('/admin/library/blog-pages')->assertInertia(fn (Assert $p) => $p->component('Library/BlogPageChanges')
        ->where('changes.0.id', $profile->id)->where('changes.0.live.theme', 'akuru')->where('changes.0.next.theme', 'night')->where('changes.0.next.tagline', 'Stories from the islands'));
    $csv = $web->actingAs($office)->get('/admin/library/blog-pages/export')->assertOk()->streamedContent();
    expect($csv)->toContain('pen_name')->toContain('Stories from the islands');

    $web->actingAs($office)->post("/admin/library/blog-pages/{$profile->id}/decide", ['decision' => 'approve'])->assertSessionHas('success', __('blog.flash_page_approved'));
    $profile->refresh();
    expect($profile->theme_key)->toBe('night')->and($profile->accent_color)->toBe('#F2C778')->and($profile->heading_font)->toBe('Lora')
        ->and($profile->bio)->toBe("I write about gardens.\nAnd the sea.")->and($profile->pending_page)->toBeNull()->and($profile->page_submitted_at)->toBeNull();

    auth()->logout();
    $page = $web->get("/library/authors/{$profile->slug}")->assertOk();
    $page->assertSee('data-blog-theme="night"', false)->assertSee('--blog-accent: #F2C778', false)->assertSee('Stories from the islands')
        ->assertSee('fonts.googleapis.com/css2?family=Lora', false)->assertSee('ދިވެހި');
    // The writer's order: about, then the latest; series switched off.
    $page->assertSeeInOrder([__('public.blogs_about'), 'I write about gardens.', __('public.blogs_posts_heading'), 'The garden begins'])->assertDontSee('data-testid="blog-section-series"', false);
});

it('refuses colours that do not read, with the reason, and saves nothing', function () {
    [$user, $profile] = bl3Blog();
    test()->withoutLocalizationMiddleware()->actingAs($user)->post('/my-blog/design', bl3Design(['theme' => 'akuru', 'accent' => '#FFF8F0']))
        ->assertSessionHasErrors(['accent' => __('blog.error_contrast', ['ratio' => '1.0', 'needed' => '3.0'])]);
    expect($profile->fresh()->pending_page)->toBeNull();

    // A theme or font not on the lists falls back; sections are each kept once.
    test()->withoutLocalizationMiddleware()->actingAs($user)->post('/my-blog/design', bl3Design(['theme' => 'neon', 'accent' => null, 'font' => 'Comic Sans', 'sections' => [['key' => 'latest', 'on' => true], ['key' => 'latest', 'on' => false], ['key' => 'tips', 'on' => true]]]));
    $draft = $profile->fresh()->pending_page;
    expect($draft['theme'])->toBe('akuru')->and($draft['font'])->toBeNull()
        ->and(collect($draft['sections'])->pluck('key')->all())->toBe(['latest', 'pinned', 'series', 'works', 'about']);
});

it('sends a refused set back with a note, and keeps the office list behind its gate', function () {
    [$user, $profile] = bl3Blog();
    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($user)->post('/my-blog/design', bl3Design());
    $web->actingAs($user)->post('/my-blog/design/submit');

    $web->actingAs($user)->get('/admin/library/blog-pages')->assertForbidden();
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->post("/admin/library/blog-pages/{$profile->id}/decide", ['decision' => 'refuse'])->assertSessionHasErrors('note');
    $web->actingAs($office)->post("/admin/library/blog-pages/{$profile->id}/decide", ['decision' => 'refuse', 'note' => 'Please leave out the island name.'])->assertSessionHas('success');

    $profile->refresh();
    expect($profile->theme_key)->toBeNull()->and($profile->pending_page)->not->toBeNull()->and($profile->page_submitted_at)->toBeNull();
    $web->actingAs($user)->get('/my-blog/design')->assertInertia(fn (Assert $p) => $p->where('state', 'draft')->where('decision_note', 'Please leave out the island name.'));
    // Nothing to send for someone with no blog; the designer sends them to My blog.
    $stranger = User::factory()->create();
    $web->actingAs($stranger)->get('/my-blog/design')->assertRedirect(route('my-blog'));
    $web->actingAs($stranger)->post('/my-blog/design/submit')->assertSessionHasErrors('page');
});

it('names the designer and the office list in three languages, and the colour check is shared with the Bookstore', function () {
    expect(\App\Support\Navigation\NavigationMap::hrefs())->toContain('/admin/library/blog-pages')
        ->and(class_exists(\App\Support\Theme\Contrast::class))->toBeTrue()
        ->and(class_exists('App\\Domains\\Bookshop\\Support\\Contrast'))->toBeFalse();
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['blog.design_title', 'blog.section_about', 'blog.error_contrast', 'nav.blog_pages', 'public.blogs_about'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
    expect(__('blog.design_title', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('blog.design_title', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
