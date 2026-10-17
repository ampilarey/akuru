<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Media\Models\MediaFile;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL3b (STATUS §5sj): a blog page's banner, picture and links —
 * private until the office approves, and for a student only with what the
 * parent allowed.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function bl3bWriter(): array
{
    $user = User::factory()->create();
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'display_name' => 'Adult Writer', 'slug' => 'adult-'.$user->id, 'status' => 'active', 'approved_at' => now(), 'blog_title' => 'Reading notes']);
    $user->assignRole('writer');

    return [$user, $profile];
}

/** A student under 18 with an approved blog, their verified parent, and the request. */
function bl3bStudent(bool $showPhoto = false): array
{
    $student = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Rasheed', 'date_of_birth' => '2013-05-01']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'mother', true);
    $user = User::query()->findOrFail($student->user_id);
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now(), 'show_photo' => $showPhoto]);
    $user->assignRole('writer');
    BlogRequest::query()->create(['user_id' => $user->id, 'student_id' => $student->id, 'pen_name' => 'Starling', 'blog_title' => 'Notes', 'about' => 'Stories I write.', 'under_18' => true, 'status' => 'approved']);

    return [$user, $profile, User::query()->findOrFail($guardian->user_id), $student];
}

function bl3bSave(User $user, array $data = []): \Illuminate\Testing\TestResponse
{
    return test()->withoutLocalizationMiddleware()->actingAs($user)->post('/my-blog/design', $data + ['theme' => 'ocean', 'languages' => ['en'], 'sections' => [['key' => 'latest', 'on' => true]]]);
}

it('keeps a new banner and picture private until the office approves, then shows them and the links', function () {
    [$user, $profile] = bl3bWriter();
    bl3bSave($user, [
        'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 400),
        'photo' => UploadedFile::fake()->image('me.png', 400, 400),
        'links' => ['youtube' => 'https://youtube.com/@reading', 'website' => 'http://insecure.example', 'x' => 'not a url'],
    ])->assertSessionHas('success');

    $draft = $profile->fresh()->pending_page;
    expect($draft['links'])->toBe(['youtube' => 'https://youtube.com/@reading'])
        ->and(MediaFile::query()->find($draft['banner'])->visibility)->toBe('private')
        ->and(MediaFile::query()->find($draft['photo'])->visibility)->toBe('private');

    $web = test()->withoutLocalizationMiddleware();
    $web->actingAs($user)->get('/my-blog/design/image/banner')->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
    $web->actingAs($user)->get('/my-blog/design')->assertInertia(fn (Assert $p) => $p->where('images.banner', fn ($url) => str_contains($url, '/my-blog/design/image/banner')));
    // Nobody else's route reaches it.
    $web->actingAs(User::factory()->create())->get('/my-blog/design/image/banner')->assertNotFound();
    $web->actingAs($user)->get("/admin/library/blog-pages/{$profile->id}/image/banner")->assertForbidden();

    $web->actingAs($user)->post('/my-blog/design/submit');
    $web->get("/library/authors/{$profile->slug}")->assertDontSee('data-testid="blog-banner"', false)->assertDontSee('youtube.com/@reading');

    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get("/admin/library/blog-pages/{$profile->id}/image/photo")->assertOk()->assertHeader('Content-Type', 'image/png');
    $web->actingAs($office)->get('/admin/library/blog-pages')->assertInertia(fn (Assert $p) => $p->where('changes.0.next.links.youtube', 'https://youtube.com/@reading')
        ->where('changes.0.next_images.banner', fn ($url) => str_contains($url, "/admin/library/blog-pages/{$profile->id}/image/banner")));
    $web->actingAs($office)->post("/admin/library/blog-pages/{$profile->id}/decide", ['decision' => 'approve'])->assertSessionHas('success');

    $profile->refresh();
    expect(MediaFile::query()->find($profile->banner_media_file_id)->visibility)->toBe('public')
        ->and(MediaFile::query()->find($profile->photo_media_file_id)->visibility)->toBe('public')
        ->and($profile->banner_media_file_id)->not->toBe($draft['banner'])
        ->and($profile->social_links)->toBe(['youtube' => 'https://youtube.com/@reading']);
    auth()->logout();
    $web->get("/library/authors/{$profile->slug}")->assertSee('data-testid="blog-banner"', false)->assertSee('https://youtube.com/@reading');

    // Removing the banner is a change like any other, live once approved.
    bl3bSave($user, ['banner_remove' => true]);
    expect($profile->fresh()->pending_page['banner'])->toBeNull()->and($profile->fresh()->pending_page['photo'])->toBe($profile->photo_media_file_id);
});

it('refuses a student\'s picture without the parent\'s photo choice', function () {
    [$child, $profile] = bl3bStudent(showPhoto: false);
    bl3bSave($child, ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertSessionHasErrors('photo');
    expect($profile->fresh()->pending_page)->toBeNull()->and(MediaFile::query()->count())->toBe(0);
    test()->withoutLocalizationMiddleware()->actingAs($child)->get('/my-blog/design')->assertInertia(fn (Assert $p) => $p->where('photo_allowed', false));

    [$other] = bl3bStudent(showPhoto: true);
    bl3bSave($other, ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertSessionHas('success');
});

it('lets an under-18 student\'s blog carry links only while the parent allows them', function () {
    [$child, $profile, $parent, $student] = bl3bStudent();
    $web = test()->withoutLocalizationMiddleware();
    bl3bSave($child, ['links' => ['youtube' => 'https://youtube.com/@starling']])->assertSessionHasErrors('links');
    $web->actingAs($child)->get('/my-blog/design')->assertInertia(fn (Assert $p) => $p->where('links_allowed', false));

    // The parent allows links on the child's blog page in the family portal.
    $web->actingAs($parent)->get("/portal/children/{$student->id}/blog")->assertInertia(fn (Assert $p) => $p->where('links_allowed', false));
    $stranger = User::query()->findOrFail(makeGuardian()->user_id);
    $web->actingAs($stranger)->post("/portal/children/{$student->id}/blog/links", ['allow' => true])->assertForbidden();
    $web->actingAs($parent)->post("/portal/children/{$student->id}/blog/links", ['allow' => true])->assertSessionHas('success', __('blog.flash_links_allowed'));

    bl3bSave($child, ['links' => ['youtube' => 'https://youtube.com/@starling']])->assertSessionHas('success');
    $web->actingAs($child)->post('/my-blog/design/submit');
    $web->actingAs(actingSystemAdmin(['library.manage']))->post("/admin/library/blog-pages/{$profile->id}/decide", ['decision' => 'approve'])->assertSessionHas('success');
    auth()->logout();
    $web->get("/library/authors/{$profile->slug}")->assertSee('https://youtube.com/@starling');

    // Stopped, the links come off at once, though they are still on the profile.
    $web->actingAs($parent)->post("/portal/children/{$student->id}/blog/links", ['allow' => false])->assertSessionHas('success', __('blog.flash_links_stopped'));
    auth()->logout();
    $web->get("/library/authors/{$profile->slug}")->assertDontSee('https://youtube.com/@starling');
    expect(__('blog.links_allow', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('blog.links_allow', [], 'ar'))->toMatch('/\p{Arabic}/u')
        ->and(__('people.consent_type_blog_links', [], 'ar'))->not->toBe('people.consent_type_blog_links');
});
