<?php

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Domains\Website\Actions\ForgetHomePageCacheAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * BACKLOG C16 slice N2 (STATUS §5nx), from the owner's walk of the live CMS
 * course form: "Slug * what is this?", "Cover Image URL *", "there is no
 * cat", "in the website it doesnt show any course". The address is filled
 * from the title and made unique; the cover is an upload; the categories
 * have a screen; a course is published to the website from the list, and
 * the home page's cache is forgotten so it shows at once.
 */
it('fills the address from the title, keeps a typed one, and refuses one another course holds', function () {
    $super = actingSystemAdmin();
    $category = CourseCategory::query()->create(['name' => 'Tajweed', 'slug' => 'tajweed', 'order' => 1]);
    $post = fn (array $extra) => $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.store'), array_merge([
        'course_category_id' => $category->id, 'title' => 'Tajweed for Beginners', 'short_desc' => 'Read well.', 'body' => '<p>Hello</p>',
        'language' => 'ar', 'level' => 'adult', 'status' => 'open',
    ], $extra));

    // No address typed: the title's, and no cover is fine.
    $post(['slug' => ''])->assertRedirect(route('admin.courses.index'))->assertSessionHasNoErrors();
    $first = Course::query()->where('title', 'Tajweed for Beginners')->sole();
    expect($first->slug)->toBe('tajweed-for-beginners')->and($first->cover_image)->toBeNull()
        ->and($first->workflow_status)->toBe(CourseWorkflowStatus::Draft);

    // The same title again: the address takes a number rather than failing.
    $post([])->assertSessionHasNoErrors();
    expect(Course::query()->where('slug', 'tajweed-for-beginners-2')->exists())->toBeTrue();

    // A typed address is kept as typed (slugified), and refused when a live course holds it.
    $post(['slug' => 'Evening Class', 'title' => 'Evening Class'])->assertSessionHasNoErrors();
    expect(Course::query()->where('slug', 'evening-class')->exists())->toBeTrue();
    $post(['slug' => 'evening-class'])->assertSessionHasErrors(['slug' => 'Another course already uses this address.']);

    // A deleted course's address is refused by name, as before.
    Course::query()->where('slug', 'evening-class')->sole()->delete();
    $post(['slug' => 'evening-class'])->assertSessionHasErrors('slug');
    expect(session('errors')->first('slug'))->toContain('Evening Class');
});

it('stores an uploaded cover as public media and shows the current one on the form', function () {
    Storage::fake('public');
    $super = actingSystemAdmin();
    $category = CourseCategory::query()->create(['name' => 'Arabic', 'slug' => 'arabic', 'order' => 1]);

    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.store'), [
        'course_category_id' => $category->id, 'title' => 'Arabic I', 'short_desc' => 's', 'body' => '<p>b</p>',
        'language' => 'ar', 'level' => 'all', 'status' => 'open',
        'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
    ])->assertRedirect(route('admin.courses.index'))->assertSessionHasNoErrors();
    $course = Course::query()->where('slug', 'arabic-i')->sole();
    expect($course->cover_image)->toStartWith('course-covers/');
    Storage::disk('public')->assertExists($course->cover_image);

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.edit', $course))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/CourseForm')
            ->where('course.cover_url', asset('storage/'.$course->cover_image))
            ->where('t.courses_slug_label', 'Web address')->where('t.courses_cover', 'Cover image (JPEG, PNG or WebP, up to 5 MB)'));

    // An update without a file keeps the cover; a PDF is refused.
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.courses.update', $course), [
        'course_category_id' => $category->id, 'title' => 'Arabic I', 'slug' => 'arabic-i', 'short_desc' => 's', 'body' => '<p>b</p>',
        'language' => 'ar', 'level' => 'all', 'status' => 'open',
    ])->assertSessionHasNoErrors();
    expect($course->fresh()->cover_image)->toBe($course->cover_image);
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.courses.update', $course), [
        'course_category_id' => $category->id, 'title' => 'Arabic I', 'slug' => 'arabic-i', 'short_desc' => 's', 'body' => '<p>b</p>',
        'language' => 'ar', 'level' => 'all', 'status' => 'open', 'cover' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('cover');
});

it('lists, adds, renames, reorders and deletes course categories, and keeps one in use', function () {
    $super = actingSystemAdmin();
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.categories'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/CourseCategories')->has('categories', 0)->where('t.courses_categories_title', 'Course categories'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.categories.store'), ['name' => 'Quran Memorization', 'order' => 2])
        ->assertRedirect()->assertSessionHas('success', 'Category saved.');
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.categories.store'), ['name' => 'Arabic'])->assertSessionHasNoErrors();
    $quran = CourseCategory::query()->where('slug', 'quran-memorization')->sole();
    $arabic = CourseCategory::query()->where('slug', 'arabic')->sole();
    expect($quran->order)->toBe(2)->and($arabic->order)->toBe(0);

    // The same address again is refused.
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.categories.store'), ['name' => 'Arabic'])->assertSessionHasErrors('slug');

    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.courses.categories.update', $quran->id), ['name' => 'Qur’an memorisation', 'order' => 1])->assertSessionHasNoErrors();
    expect($quran->fresh()->name)->toBe('Qur’an memorisation')->and($quran->fresh()->order)->toBe(1)->and($quran->fresh()->slug)->toBe('quran-memorisation');

    // The list is in order, with how many courses use each; a category in use stays.
    Course::query()->create(['course_category_id' => $arabic->id, 'title' => 'Arabic I', 'slug' => 'arabic-i', 'short_desc' => 's', 'body' => '<p>b</p>', 'language' => 'ar', 'level' => 'all', 'status' => 'open', 'course_type' => 'general', 'workflow_status' => 'draft']);
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.categories'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('categories.0.slug', 'arabic')->where('categories.0.courses', 1)->where('categories.1.slug', 'quran-memorisation')->where('categories.1.courses', 0));
    $this->withoutLocalizationMiddleware()->actingAs($super)->delete(route('admin.courses.categories.destroy', $arabic->id))->assertSessionHasErrors('category');
    expect(session('errors')->first('category'))->toContain('"Arabic" has 1 course');
    expect(CourseCategory::query()->whereKey($arabic->id)->exists())->toBeTrue();

    $this->withoutLocalizationMiddleware()->actingAs($super)->delete(route('admin.courses.categories.destroy', $quran->id))->assertSessionHas('success', 'Category deleted.');
    expect(CourseCategory::query()->whereKey($quran->id)->exists())->toBeFalse();

    // The form says when there are none, and the list links to the screen.
    Course::query()->forceDelete();
    CourseCategory::query()->delete();
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('categories_count', 0)->where('t.courses_no_categories', 'No categories yet — add one first.'));

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['courses_slug_label', 'courses_slug_hint', 'courses_cover', 'courses_categories_title', 'courses_category_in_use', 'courses_publish_hint', 'courses_flash_published', 'courses_not_listed'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});

it('publishes a course to the website from the CMS list, walking the workflow and forgetting the home cache', function () {
    $super = actingSystemAdmin();
    $category = CourseCategory::query()->create(['name' => 'Fiqh', 'slug' => 'fiqh', 'order' => 1]);
    $make = fn (string $slug, string $status, string $workflow) => Course::query()->create(['course_category_id' => $category->id, 'title' => ucfirst($slug), 'slug' => $slug, 'short_desc' => 's', 'body' => '<p>b</p>', 'cover_image' => null, 'language' => 'en', 'level' => 'all', 'status' => $status, 'course_type' => 'general', 'workflow_status' => $workflow]);
    $draft = $make('fiqh-one', 'open', 'draft');
    $closed = $make('fiqh-two', 'closed', 'published');
    $archived = $make('fiqh-three', 'open', 'archived');

    // The list says what is on the website and why not.
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_publish', true)->where('categories_count', 1)
            ->where('courses.0.workflow_status', 'draft')->where('courses.0.on_website', false)
            ->where('courses.2.workflow_status', 'published')->where('courses.2.on_website', false)
            ->where('courses.1.workflow_status', 'archived'));

    Cache::put(ForgetHomePageCacheAction::key('en'), ['stale'], 600);
    Cache::put(ForgetHomePageCacheAction::key('dv'), ['stale'], 600);
    $this->withoutLocalizationMiddleware()->actingAs($super)->from(route('admin.courses.index'))->post(route('admin.courses.publish', $draft))
        ->assertRedirect(route('admin.courses.index'))->assertSessionHas('success', '"Fiqh-one" is on the website.');
    expect($draft->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Published)
        ->and(Cache::has(ForgetHomePageCacheAction::key('en')))->toBeFalse()
        ->and(Cache::has(ForgetHomePageCacheAction::key('dv')))->toBeFalse()
        ->and(ForgetHomePageCacheAction::key('en'))->toBe('homepage_data_v8_en');

    // Published and open: listed on the public courses page.
    $this->withoutLocalizationMiddleware()->get(route('public.courses.index'))->assertOk()->assertSee('Fiqh-one');
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.index'))
        ->assertInertia(fn (Assert $page) => $page->where('courses.0.on_website', true));

    // Publishing again is a no-op; an archived course is refused with a reason.
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.publish', $draft))->assertSessionHas('success');
    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.publish', $archived))->assertSessionHasErrors(['workflow_status' => 'An archived course cannot be published again.']);
    expect($archived->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Archived)->and($closed->fresh()->status)->toBe('closed');

    // The CMS is the system admin's (`role:super_admin`), who passes every
    // gate by design (Gate::before), so the `courses.publish` refusal cannot
    // be reached over HTTP here; the engine's own gate is pinned in
    // `TransitionCourseWorkflowAction`'s tests and read back as `can_publish`.
});
