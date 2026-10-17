<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryFollow;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL7 (STATUS §5so): Featured this month and its reward, reads per
 * post, the blogs' insights, and a student blogger becoming a writer at 18.
 */
uses(RefreshDatabase::class);

function bl7Blog(string $born = '2012-04-01'): array
{
    $student = makeStudent(['date_of_birth' => $born]);
    $guardian = makeGuardian();
    $user = User::query()->findOrFail($student->user_id);
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now(), 'guardian_user_id' => $guardian->user_id, 'guardian_consented_at' => now()->subYear(), 'show_photo' => false]);
    $post = LibraryItem::query()->create(['title' => 'The garden begins', 'slug' => 'garden-'.$user->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now()->subDays(3), 'readers_notified_at' => now(), 'writer_id' => $profile->id, 'body' => '<p>Beans.</p>']);

    return [$user, $profile, $post, User::query()->findOrFail($guardian->user_id)];
}

it('features a post this month on the blogs\' front, and rewards it only when rewards are on, up to the cap, once', function () {
    [$owner, $profile, $post] = bl7Blog();
    $office = actingSystemAdmin(['library.manage']);
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($office)->post("/admin/library/blog-insights/{$post->id}/feature", ['feature' => true, 'reward' => 50])->assertSessionHasErrors('reward');
    expect($post->fresh()->featured)->toBeFalse();
    $web->actingAs($office)->post("/admin/library/blog-insights/{$post->id}/feature", ['feature' => true])->assertSessionHas('success', __('blog.flash_featured'));
    auth()->logout();
    $web->get('/library/blogs')->assertSee('data-testid="blogs-featured"', false)->assertSee('data-featured="'.$post->slug.'"', false);
    expect(DB::table('user_notifications')->where('user_id', $owner->id)->count())->toBe(1);

    config(['library.blog.reward_enabled' => true, 'library.blog.reward_cap' => 100]);
    $web->actingAs($office)->post("/admin/library/blog-insights/{$post->id}/feature", ['feature' => true, 'reward' => 150])->assertSessionHasErrors('reward');
    $web->actingAs($office)->post("/admin/library/blog-insights/{$post->id}/feature", ['feature' => true, 'reward' => 75])->assertSessionHas('success');
    $reward = WriterEarning::query()->sole();
    expect($reward->kind)->toBe('reward')->and((float) $reward->writer_amount)->toBe(75.0)->and((float) $reward->platform_commission)->toBe(0.0)
        ->and((float) $reward->bml_amount)->toBe(0.0)->and((float) $reward->wallet_amount)->toBe(0.0)->and($reward->library_tip_id)->toBeNull()->and($reward->library_purchase_id)->toBeNull()
        ->and($reward->writer_id)->toBe($profile->id);
    $web->actingAs($office)->post("/admin/library/blog-insights/{$post->id}/feature", ['feature' => true, 'reward' => 10])->assertSessionHasErrors('reward');

    $web->actingAs($office)->post("/admin/library/blog-insights/{$post->id}/feature", ['feature' => false])->assertSessionHas('success');
    auth()->logout();
    $web->get('/library/blogs')->assertDontSee('data-testid="blogs-featured"', false);
});

it('counts a post\'s readers for its writer and the office, and shows the blogs\' insights with a CSV', function () {
    [$owner, $profile, $post] = bl7Blog();
    foreach (['s1', 's1', 's2', 's3'] as $session) {
        LibraryReadingEvent::query()->create(['library_item_id' => $post->id, 'page_number' => 1, 'kind' => 'page_view', 'session_hash' => $session, 'occurred_at' => now()]);
    }
    LibraryFollow::query()->create(['user_id' => User::factory()->create()->id, 'writer_id' => $profile->id]);
    LibraryItem::query()->create(['title' => 'Waiting', 'slug' => 'waiting-'.$owner->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'submitted', 'review_stage' => 'teacher', 'submitted_at' => now()->subDays(4), 'writer_id' => $profile->id]);

    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($owner)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('posts', fn ($posts) => collect($posts)->firstWhere('id', $post->id)['reads'] === 3));

    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get('/admin/library/blog-insights')->assertInertia(fn (Assert $p) => $p->component('Library/BlogInsights')
        ->where('totals.students', 1)->where('totals.posts_published', 1)->where('totals.follows', 1)
        ->where('queues.teacher.count', 1)->where('recent.0.reads', 3)->where('reward.enabled', false));
    expect($web->actingAs($office)->get('/admin/library/blog-insights/export')->assertOk()->streamedContent())->toContain('The garden begins')->toContain(',3,');
    $web->actingAs($owner)->get('/admin/library/blog-insights')->assertForbidden();
});

it('makes a student who has turned 18 a writer, keeping the page, posts, followers and earnings, and tells both', function () {
    [$adult, $profile, $post, $parent] = bl7Blog(now()->subYears(18)->subDay()->toDateString());
    [$young, $youngProfile] = bl7Blog();
    LibraryFollow::query()->create(['user_id' => User::factory()->create()->id, 'writer_id' => $profile->id]);
    WriterEarning::query()->create(['writer_id' => $profile->id, 'kind' => 'reward', 'library_item_id' => $post->id, 'gross_amount' => 20, 'discount_amount' => 0, 'wallet_amount' => 0, 'bml_amount' => 0, 'platform_commission' => 0, 'writer_amount' => 20, 'status' => 'available']);

    $this->artisan('library:blogs-growing-up')->expectsOutputToContain('1')->assertSuccessful();

    $profile->refresh();
    expect($profile->kind)->toBe('writer')->and($profile->guardian_user_id)->toBeNull()->and($profile->guardian_consented_at)->toBeNull()
        ->and($profile->slug)->toBe('starling-'.$adult->id)
        ->and(LibraryItem::query()->where('writer_id', $profile->id)->count())->toBe(1)
        ->and(LibraryFollow::query()->where('writer_id', $profile->id)->count())->toBe(1)
        ->and(WriterEarning::query()->where('writer_id', $profile->id)->count())->toBe(1)
        ->and($youngProfile->fresh()->kind)->toBe('student');
    expect(DB::table('user_notifications')->where('user_id', $adult->id)->exists())->toBeTrue()
        ->and(DB::table('user_notifications')->where('user_id', $parent->id)->exists())->toBeTrue();
    // Run again, nothing moves twice.
    $this->artisan('library:blogs-growing-up')->expectsOutputToContain('0')->assertSuccessful();
    test()->withoutLocalizationMiddleware()->get("/library/authors/{$profile->slug}")->assertOk()->assertSee('The garden begins');
});

it('links the owner\'s writing guidelines from My blog once their address is set', function () {
    [$owner] = bl7Blog();
    test()->withoutLocalizationMiddleware()->actingAs($owner)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('guidelines_url', null));
    config(['library.blog.guidelines_url' => '/page/blog-guidelines']);
    test()->withoutLocalizationMiddleware()->actingAs($owner)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('guidelines_url', '/page/blog-guidelines'));
});

it('names the insights and the featured posts in three languages', function () {
    expect(\App\Support\Navigation\NavigationMap::hrefs())->toContain('/admin/library/blog-insights');
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['blog.insights_title', 'blog.insights_feature', 'public.blogs_featured', 'nav.blog_insights', 'admin.library_settings_label_reward'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
    expect(__('public.blogs_featured', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('public.blogs_featured', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
