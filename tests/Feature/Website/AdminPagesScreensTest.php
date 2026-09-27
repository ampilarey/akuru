<?php

use App\Domains\Website\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The pages CMS screens (docs/ADMIN_PANEL.md; C9 slice 10, STATUS §5jl):
 * Inertia pages with every string keyed EN/DV/AR — the list, the form for
 * new and edit, the preview — with the body still sanitised on every write.
 */
it('lists, creates, previews, edits and deletes a page as props, sanitising the body and keying the flashes', function () {
    $super = actingSystemAdmin();

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.pages.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/PageForm')->where('page', null)->where('t.pages_new_title', 'Create New Page'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->post(route('admin.pages.store'), [
        'title' => 'About us', 'slug' => 'about-us', 'excerpt' => 'Who we are.', 'body' => '<p>Hello</p><script>alert(1)</script>', 'is_published' => 1,
    ])->assertRedirect(route('admin.pages.index'))->assertSessionHas('success', 'Page created successfully.');
    $row = Page::query()->where('slug', 'about-us')->sole();
    expect($row->body)->toContain('<p>Hello</p>')->not->toContain('<script')->and($row->is_published)->toBeTrue()->and($row->published_at)->not->toBeNull();

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.pages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Pages')
            ->where('total', 1)->where('pages.0.title', 'About us')->where('pages.0.slug', 'about-us')->where('pages.0.is_published', true)
            ->where('pages.0.public_url', route('public.page.show', 'about-us'))->where('pages.0.body', null)
            ->where('t.pages_title', 'Manage Pages')->where('t.pages_delete_confirm', 'Are you sure you want to delete this page?'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.pages.show', $row))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/PagePreview')->where('page.body', fn ($body) => str_contains($body, '<p>Hello</p>'))->where('page.excerpt', 'Who we are.'));
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.pages.edit', $row))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/PageForm')->where('page.id', $row->id)->where('page.is_published', true));

    // Unpublishing (the flag absent, as the form sends it) clears the date; a duplicate slug is a field error.
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.pages.update', $row), ['title' => 'About us, revised', 'slug' => 'about-us', 'body' => '<p>Hi</p>'])
        ->assertRedirect(route('admin.pages.index'))->assertSessionHas('success', 'Page updated successfully.');
    $row->refresh();
    expect($row->title)->toBe('About us, revised')->and($row->is_published)->toBeFalse()->and($row->published_at)->toBeNull();
    Page::query()->create(['title' => 'Other', 'slug' => 'other', 'body' => '<p>o</p>']);
    $this->withoutLocalizationMiddleware()->actingAs($super)->put(route('admin.pages.update', $row), ['title' => 'x', 'slug' => 'other', 'body' => '<p>x</p>'])->assertSessionHasErrors('slug');

    $this->withoutLocalizationMiddleware()->actingAs($super)->delete(route('admin.pages.destroy', $row))->assertRedirect(route('admin.pages.index'))->assertSessionHas('success', 'Page deleted successfully.');
    expect(Page::query()->whereKey($row->id)->exists())->toBeFalse();

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['pages_title', 'pages_new', 'pages_content_hint', 'pages_delete_confirm', 'pages_flash_created'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // The website is the system admin's (ADR-040 slice 2).
    $admin = \App\Domains\Identity\Models\User::factory()->create();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.pages.index'))->assertForbidden();
});
