<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The courses CMS screens (docs/ADMIN_PANEL.md; C9 slice 11, STATUS §5jm):
 * Inertia pages with every string keyed EN/DV/AR — the list, the form for
 * new and edit with its outcomes and CTA fields — with the body still
 * sanitised on every write and the deleted-slug refusal still naming the
 * course that holds the address.
 */
it('lists, creates, edits and deletes a course as props, sanitising the body and keying the flashes', function () {
    $super = actingSystemAdmin();
    $category = CourseCategory::query()->create(['name' => 'Quran', 'slug' => 'quran-'.uniqid(), 'order' => 1]);

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/CourseForm')->where('course', null)
            ->where('categories.0.name', 'Quran')->where('t.courses_new_title', 'Create New Course')->where('t.courses_level_kids', 'Kids'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.courses.store'), [
        'course_category_id' => $category->id, 'title' => 'Tajweed basics', 'slug' => 'tajweed-basics', 'short_desc' => 'Read well.',
        'body' => '<p>Hello</p><script>alert(1)</script>', 'cover_image' => '/images/t.jpg', 'language' => 'ar', 'level' => 'adult', 'status' => 'upcoming',
        'fee' => '250', 'seats' => '20', 'learning_outcomes_en' => "Recite\nListen", 'learning_outcomes_dv' => '', 'learning_outcomes_ar' => 'اقرأ',
        'whatsapp_number' => '+960 777 1234', 'syllabus_media_file_id' => '',
    ])->assertRedirect(route('admin.courses.index'))->assertSessionHas('success', 'Course created successfully.');
    $row = Course::query()->where('slug', 'tajweed-basics')->sole();
    expect($row->body)->toContain('<p>Hello</p>')->not->toContain('<script')
        ->and($row->learning_outcomes['en'])->toBe(['Recite', 'Listen'])->and($row->learning_outcomes['ar'])->toBe(['اقرأ'])
        ->and($row->whatsapp_number)->toBe('9607771234')->and($row->syllabus_media_file_id)->toBeNull();

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Courses')
            ->where('total', 1)->where('courses.0.title', 'Tajweed basics')->where('courses.0.slug', 'tajweed-basics')
            ->where('courses.0.category', 'Quran')->where('courses.0.status', 'upcoming')->where('courses.0.short_desc', 'Read well.')
            ->where('t.courses_title', 'Manage Courses')->where('t.courses_status_upcoming', 'Upcoming')->where('t.courses_delete_confirm', 'Are you sure you want to delete this course?'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.edit', $row))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/CourseForm')->where('course.id', $row->id)->where('course.slug', 'tajweed-basics')
            ->where('course.course_category_id', $category->id)->where('course.learning_outcomes_en', "Recite\nListen")->where('course.learning_outcomes_ar', 'اقرأ')
            ->where('course.whatsapp_number', '9607771234')->where('course.language', 'ar'));

    // An update keeps the write path: the body sanitised, the outcomes and CTA through their Actions.
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.courses.update', $row), [
        'course_category_id' => $category->id, 'title' => 'Tajweed, revised', 'slug' => 'tajweed-basics', 'short_desc' => 'Read well.',
        'body' => '<p>Hi</p><img src=x onerror=alert(1)>', 'cover_image' => '/images/t.jpg', 'language' => 'en', 'level' => 'all', 'status' => 'open',
        'learning_outcomes_en' => '', 'learning_outcomes_dv' => '', 'learning_outcomes_ar' => '', 'whatsapp_number' => '',
    ])->assertRedirect(route('admin.courses.index'))->assertSessionHas('success', 'Course updated successfully.');
    $row->refresh();
    expect($row->title)->toBe('Tajweed, revised')->and($row->body)->not->toContain('onerror')->and($row->learning_outcomes)->toBeNull()
        ->and($row->whatsapp_number)->toBeNull()->and($row->status)->toBe('open');

    // A slug a live course holds is a field error; one a deleted course holds names that course.
    $other = Course::query()->create(['course_category_id' => $category->id, 'title' => 'Other', 'slug' => 'other', 'short_desc' => 'o', 'body' => '<p>o</p>', 'cover_image' => 'c.jpg', 'language' => 'en', 'level' => 'all', 'status' => 'open', 'course_type' => 'general', 'workflow_status' => 'draft']);
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.courses.update', $row), [
        'course_category_id' => $category->id, 'title' => 'x', 'slug' => 'other', 'short_desc' => 'x', 'body' => '<p>x</p>', 'cover_image' => 'c.jpg', 'language' => 'en', 'level' => 'all', 'status' => 'open',
    ])->assertSessionHasErrors('slug');

    $this->withoutLocalizationMiddleware()->actingAs($super)->delete(route('admin.courses.destroy', $other))
        ->assertRedirect(route('admin.courses.index'))->assertSessionHas('success', 'Course deleted.');
    expect(Course::withTrashed()->where('slug', 'other')->exists())->toBeFalse();

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['courses_title', 'courses_new', 'courses_outcomes_hint', 'courses_delete_confirm', 'courses_flash_created', 'courses_slug_held', 'courses_flash_kept'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // The website is the system admin's (ADR-040 slice 2).
    $admin = \App\Domains\Identity\Models\User::factory()->create();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.courses.index'))->assertForbidden();
});
