<?php

use App\Domains\HR\Models\Instructor;
use App\Domains\Website\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The research screens (docs/ADMIN_PANEL.md; C9 slice 8, STATUS §5jj):
 * Inertia pages with every string keyed EN/DV/AR, the form serving new and
 * edit alike, and the action's validation surfacing as field errors.
 */
it('lists the posts with the filters, serves the form for new and edit, and surfaces the action’s validation', function () {
    $super = actingSystemAdmin();
    $author = Instructor::query()->create(['name' => 'Ustadha Author', 'slug' => 'ustadha-author', 'is_active' => true, 'sort_order' => 1]);

    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.research.store'), [
        'title' => 'On Tajweed', 'slug' => 'on-tajweed', 'abstract' => 'A.', 'body' => '<p>B</p>', 'instructor_ids' => [$author->id],
        'is_published' => '1', 'published_at' => '2025-03-01T10:00',
    ])->assertRedirect()->assertSessionHas('success', 'Research post saved.');
    $post = Post::query()->where('slug', 'on-tajweed')->sole();

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.research.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Research')
            ->where('posts.0.id', $post->id)->where('posts.0.year', 2025)->where('posts.0.authors_label', 'Ustadha Author')
            ->where('years', [2025])->where('instructors.0.name', 'Ustadha Author')
            ->where('filters', ['year' => '', 'instructor_id' => '', 'q' => ''])
            ->where('t.research_title', 'Research posts'));
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.research.index', ['year' => 2024]))
        ->assertInertia(fn (Assert $page) => $page->where('posts', [])->where('filters.year', '2024'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.research.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/ResearchForm')->where('item', null)->has('instructors', 1)->where('t.research_new_title', 'New research post'));
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.research.edit', $post))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/ResearchForm')
            ->where('item.id', $post->id)->where('item.title', 'On Tajweed')->where('item.instructor_ids', [$author->id])
            ->where('item.published_at_local', '2025-03-01T10:00')->where('item.is_published_flag', true));

    // The action's validation reaches the form as a field error; a rename keeps the slug.
    $this->withoutLocalizationMiddleware()->actingAs($super)->from(route('admin.research.edit', $post))
        ->put(route('admin.research.update', $post), ['title' => '', 'slug' => 'on-tajweed'])->assertRedirect(route('admin.research.edit', $post))->assertSessionHasErrors('title');
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.research.update', $post), ['title' => 'On Tajweed, revised', 'slug' => 'on-tajweed', 'is_published' => '0'])
        ->assertRedirect(route('admin.research.edit', $post))->assertSessionHas('success', 'Research post updated.');
    expect($post->fresh()->title)->toBe('On Tajweed, revised')->and((bool) $post->fresh()->is_published)->toBeFalse();

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['research_title', 'research_new_title', 'research_external_authors', 'research_saved', 'research_none'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});
