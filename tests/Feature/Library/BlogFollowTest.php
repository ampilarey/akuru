<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Mail\LibraryNoticeMail;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingProgress;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * BLOG_PLAN BL4 (STATUS §5sk): readers follow a blog, hear of its new posts
 * once (by email too where they asked), and can read its RSS feed.
 */
uses(RefreshDatabase::class);

function bl4Blog(string $slug = 'starling'): array
{
    $user = User::factory()->create();
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'blog_title' => 'Notes & <things>', 'slug' => $slug, 'status' => 'active', 'approved_at' => now()]);

    return [$user, $profile];
}

function bl4Post(WriterProfile $writer, string $title, string $status = 'published'): LibraryItem
{
    return LibraryItem::query()->create([
        'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.$writer->id, 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en',
        'status' => $status, 'published_at' => $status === 'published' ? now() : null, 'writer_id' => $writer->id, 'body' => '<p>'.$title.' & more.</p>',
    ]);
}

function bl4Notices(int $userId): array
{
    return DB::table('user_notifications')->where('user_id', $userId)->pluck('data')->map(fn ($d) => json_decode((string) $d, true)['href'] ?? null)->all();
}

it('lets a signed-in reader follow and stop following, never their own blog, and lists it in My Library', function () {
    [$writer, $profile] = bl4Blog();
    $reader = User::factory()->create();
    $web = test()->withoutLocalizationMiddleware();

    $web->post("/library/authors/{$profile->slug}/follow", ['follow' => true])->assertRedirect(route('login'));
    $web->get("/library/authors/{$profile->slug}")->assertOk()->assertSee('data-testid="follow-box"', false)->assertSee(__('public.follow_follow'));

    $web->actingAs($reader)->post("/library/authors/{$profile->slug}/follow", ['follow' => true])->assertSessionHas('success', __('public.follow_flash_following'));
    expect($profile->fresh()->followers_count)->toBe(1);
    $web->actingAs($reader)->get("/library/authors/{$profile->slug}")->assertSee('data-following="yes"', false)->assertSee(trans_choice('public.follow_count', 1, ['count' => 1]));
    $web->actingAs($reader)->get('/my-library')->assertOk()->assertSee('data-testid="following-starling"', false)->assertSee('Notes &amp; &lt;things&gt;', false);

    // Following twice is still one follow; email is the reader's choice.
    $web->actingAs($reader)->post("/library/authors/{$profile->slug}/follow", ['follow' => true, 'email' => true]);
    expect($profile->fresh()->followers_count)->toBe(1)->and(DB::table('library_follows')->where('user_id', $reader->id)->value('notify_email'))->toBe(1);

    $web->actingAs($writer)->post("/library/authors/{$profile->slug}/follow", ['follow' => true])->assertSessionHasErrors('follow');
    $web->actingAs($writer)->get("/library/authors/{$profile->slug}")->assertDontSee('data-testid="follow-button"', false);

    $web->actingAs($reader)->post("/library/authors/{$profile->slug}/follow", ['follow' => false])->assertSessionHas('success');
    expect($profile->fresh()->followers_count)->toBe(0);
});

it('tells followers and earlier readers of a new post once, and emails only those who asked', function () {
    Mail::fake();
    [, $profile] = bl4Blog();
    $emailFollower = User::factory()->create();
    $appFollower = User::factory()->create();
    $earlierReader = User::factory()->create();
    $stranger = User::factory()->create();
    foreach ([[$emailFollower, true], [$appFollower, false]] as [$user, $email]) {
        test()->withoutLocalizationMiddleware()->actingAs($user)->post("/library/authors/{$profile->slug}/follow", ['follow' => true, 'email' => $email]);
    }
    $old = bl4Post($profile, 'An older post');
    LibraryReadingProgress::query()->create(['user_id' => $earlierReader->id, 'library_item_id' => $old->id, 'current_page' => 1]);

    $post = bl4Post($profile, 'The garden begins', 'submitted');
    $office = actingSystemAdmin(['library.manage']);
    app(PublishLibraryItemAction::class)->execute($post->id, $office->id);

    $href = "/library/{$post->slug}";
    expect(bl4Notices($emailFollower->id))->toContain($href)->and(bl4Notices($appFollower->id))->toContain($href)
        ->and(bl4Notices($earlierReader->id))->toContain($href)->and(bl4Notices($stranger->id))->not->toContain($href);
    Mail::assertQueued(LibraryNoticeMail::class, fn (LibraryNoticeMail $mail) => $mail->hasTo($emailFollower->email));
    Mail::assertNotQueued(LibraryNoticeMail::class, fn (LibraryNoticeMail $mail) => $mail->hasTo($appFollower->email));

    // Unpublished and published again: nobody is told twice.
    app(PublishLibraryItemAction::class)->execute($post->id, $office->id, false);
    app(PublishLibraryItemAction::class)->execute($post->id, $office->id);
    expect(collect(bl4Notices($appFollower->id))->filter(fn ($h) => $h === $href)->count())->toBe(1);
});

it('serves each blog\'s RSS feed and the blogs\' feed, published posts only, escaped', function () {
    [, $profile] = bl4Blog();
    bl4Post($profile, 'Live & <well>');
    bl4Post($profile, 'Still waiting', 'submitted');
    [, $other] = bl4Blog('another');
    bl4Post($other, 'Another blog post');

    $web = test()->withoutLocalizationMiddleware();
    $feed = $web->get("/library/authors/{$profile->slug}/feed")->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent();
    $xml = simplexml_load_string($feed);
    expect($xml)->not->toBeFalse()
        ->and((string) $xml->channel->title)->toBe('Notes & <things>')
        ->and(count($xml->channel->item))->toBe(1)
        ->and((string) $xml->channel->item[0]->title)->toBe('Live & <well>')
        ->and((string) $xml->channel->item[0]->link)->toEndWith('/library/live-well-'.$profile->id)
        ->and($feed)->not->toContain('Still waiting')->not->toContain('<p>');

    $all = simplexml_load_string($web->get('/library/blogs/feed')->assertOk()->getContent());
    $titles = [];
    foreach ($all->channel->item as $item) {
        $titles[] = (string) $item->title;
    }
    sort($titles);
    expect($titles)->toBe(['Another blog post', 'Live & <well>']);
    $web->get('/library/authors/nobody/feed')->assertNotFound();
    $web->get("/library/authors/{$profile->slug}")->assertSee(route('public.library.author.feed', $profile->slug), false);
});

it('shows the follow box on a post, and says it all in three languages', function () {
    [, $profile] = bl4Blog();
    $post = bl4Post($profile, 'The garden begins');
    test()->withoutLocalizationMiddleware()->get("/library/{$post->slug}")->assertOk()->assertSee('data-testid="follow-box"', false);
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['public.follow_follow', 'public.follow_my_heading', 'public.follow_feed', 'public.follow_count'] as $key) {
            expect(__($key, [], $locale))->not->toBe($key);
        }
    }
    expect(__('public.follow_follow', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('public.follow_follow', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
