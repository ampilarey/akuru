<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryCategoryAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Library office names a category in English, Dhivehi and Arabic, and
 * renames one in place (C20, STATUS §5po).
 *
 * The table and the save action already held `name_dv` and `name_ar`, and
 * since LT6 the shelf says the name for the page's language. But the office's
 * form had one English box and no way to rename. So a category could only
 * ever be English on the Dhivehi and Arabic shelves, and a misspelt one stayed
 * misspelt.
 */
uses(RefreshDatabase::class);

function c20Category(array $data = []): LibraryCategory
{
    return app(SaveLibraryCategoryAction::class)->execute(array_merge(['name' => 'Fiqh', 'sort_order' => 3], $data));
}

function c20ShelvedIn(LibraryCategory $category): void
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'C20 Book '.uniqueFixtureSuffix(),
        'content_type' => 'book',
        'access_type' => 'free_public',
        'library_category_id' => $category->id,
        'body' => '<p>C20 page.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
}

it('adds a category with its Dhivehi and Arabic names', function () {
    $office = actingSystemAdmin(['library.manage']);

    $this->actingAs($office)
        ->post('/admin/library/categories', ['name' => 'Seerah', 'name_dv' => 'ސީރަތް', 'name_ar' => 'السيرة'])
        ->assertSessionHas('success');

    $category = LibraryCategory::query()->where('slug', 'seerah')->sole();
    expect($category->name_dv)->toBe('ސީރަތް')
        ->and($category->name_ar)->toBe('السيرة');
});

it('renames a category in three languages and keeps its address, order and state', function () {
    $office = actingSystemAdmin(['library.manage']);
    $category = c20Category(['is_active' => false]);

    $this->actingAs($office)
        ->post("/admin/library/categories/{$category->id}", ['name' => 'Fiqh and Usul', 'name_dv' => 'ފިޤުހު', 'name_ar' => 'الفقه'])
        ->assertSessionHas('success');

    $category->refresh();
    expect($category->name)->toBe('Fiqh and Usul')
        ->and($category->name_dv)->toBe('ފިޤުހު')
        ->and($category->name_ar)->toBe('الفقه')
        ->and($category->slug)->toBe('fiqh')
        ->and($category->sort_order)->toBe(3)
        ->and((bool) $category->is_active)->toBeFalse();
});

it('says the new name on the Dhivehi shelf, and the English where the Dhivehi is emptied', function () {
    $office = actingSystemAdmin(['library.manage']);
    $category = c20Category();
    c20ShelvedIn($category);

    $this->actingAs($office)->post("/admin/library/categories/{$category->id}", ['name' => 'Fiqh', 'name_dv' => 'ފިޤުހު', 'name_ar' => 'الفقه']);
    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()
        ->assertSee('ފިޤުހު');

    $this->actingAs($office)->post("/admin/library/categories/{$category->id}", ['name' => 'Fiqh', 'name_dv' => '', 'name_ar' => 'الفقه']);
    expect($category->fresh()->name_dv)->toBeNull();
    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()
        ->assertDontSee('ފިޤުހު')
        ->assertSee('Fiqh');
});

it('lists every category on the office page with its three names', function () {
    $office = actingSystemAdmin(['library.manage']);
    c20Category(['name_dv' => 'ފިޤުހު', 'name_ar' => 'الفقه']);

    $this->actingAs($office)->withoutLocalizationMiddleware()->get('/admin/library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/Admin')
            ->where('categories.0.name', 'Fiqh')
            ->where('categories.0.name_dv', 'ފިޤުހު')
            ->where('categories.0.name_ar', 'الفقه')
            ->where('categories.0.slug', 'fiqh'));
});

it('refuses a rename with no English name, in the page\'s language', function () {
    $office = actingSystemAdmin(['library.manage']);
    $category = c20Category();

    $this->actingAs($office)
        ->withHeader('Referer', url('/dv/admin/library'))
        ->post("/admin/library/categories/{$category->id}", ['name' => '', 'name_dv' => 'ފިޤުހު'])
        ->assertSessionHasErrors('name');

    expect($category->fresh()->name)->toBe('Fiqh')
        ->and($category->fresh()->name_dv)->toBeNull();
});

it('lets only the Library office rename, and only a category that exists', function () {
    $category = c20Category();

    $this->actingAs(User::factory()->create())
        ->post("/admin/library/categories/{$category->id}", ['name' => 'Taken over'])
        ->assertForbidden();
    expect($category->fresh()->name)->toBe('Fiqh');

    $this->actingAs(actingSystemAdmin(['library.manage']))
        ->post('/admin/library/categories/999999', ['name' => 'Nowhere'])
        ->assertNotFound();
});
