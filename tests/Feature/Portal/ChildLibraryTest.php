<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryBookmark;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\LibraryReadingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * B8 (LIBRARY_PLAN §10, STATUS §5io): a parent sees what a verified child
 * is reading and has bought — and not their bookmarks or notes, and not
 * anybody else's child.
 */
function childLibraryFamily(string $verification = 'verified'): array
{
    $guardian = makeGuardian();
    $child = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Ali']);
    DB::table('guardian_student')->insert([
        'guardian_id' => $guardian->id, 'student_id' => $child->id, 'relationship' => 'father', 'is_primary' => true,
        'verification_status' => $verification, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [User::query()->findOrFail($guardian->user_id), $child];
}

function childLibraryItem(string $title): LibraryItem
{
    $admin = User::factory()->create();
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => $title, 'content_type' => 'book', 'access_type' => 'free_public', 'body' => '<p>'.$title.'</p>', 'created_by' => $admin->id,
    ]);

    return app(PublishLibraryItemAction::class)->execute($item->id, $admin->id)->fresh();
}

it('shows a parent what their child is reading and has bought, but not the child\'s notes', function () {
    [$parent, $child] = childLibraryFamily();
    $book = childLibraryItem('Sun letters');
    $bought = childLibraryItem('Moon letters');

    LibraryReadingProgress::query()->create([
        'user_id' => $child->user_id, 'library_item_id' => $book->id, 'current_page' => 4, 'progress_percent' => 40, 'last_read_at' => now(),
    ]);
    LibraryBookmark::query()->create(['user_id' => $child->user_id, 'library_item_id' => $book->id, 'page_number' => 2, 'note' => 'PRIVATE-NOTE']);
    LibraryPurchase::query()->create([
        'user_id' => $child->user_id, 'library_item_id' => $bought->id, 'amount' => 50, 'currency' => 'MVR', 'status' => 'paid', 'purchased_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.children'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Children')->where('children.0.id', $child->id));

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.children.library', $child->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/ChildLibrary')
            ->where('child.name', 'Aisha Ali')
            ->where('child.has_account', true)
            ->where('continue.0.title', 'Sun letters')
            ->where('continue.0.progress_percent', 40)
            ->where('purchases.0.title', 'Moon letters')
            ->where('purchases.0.status', 'paid')
            ->missing('bookmarks'));

    $csv = $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.children.library.export', $child->id))->assertOk()->streamedContent();
    expect($csv)->toContain('kind,title,page')
        ->and($csv)->toContain('reading,"Sun letters",4,40,no')
        ->and($csv)->toContain('purchase,"Moon letters"')
        ->and($csv)->not->toContain('PRIVATE-NOTE');
});

it('refuses an unverified link, somebody else\'s child, and says when a child has no login', function () {
    [$parent, $child] = childLibraryFamily();
    [$stranger] = childLibraryFamily();
    [$unverified, $pending] = childLibraryFamily('unverified');

    $this->withoutLocalizationMiddleware()->actingAs($stranger)->get(route('portal.children.library', $child->id))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($stranger)->get(route('portal.children.library.export', $child->id))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($unverified)->get(route('portal.children.library', $pending->id))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('portal.children.library', 999999))->assertForbidden();

    $child->forceFill(['user_id' => null])->save();
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.children.library', $child->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('child.has_account', false)->where('continue', [])->where('purchases', []));
});
