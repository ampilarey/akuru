<?php

use App\Domains\Academics\Actions\ListFoundItemsAction;
use App\Domains\Academics\Actions\ReturnFoundItemAction;
use App\Domains\Academics\Actions\SaveFoundItemAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\FoundItem;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E15 — lost and found.
 *
 * Staff log what turns up; families browse what is still on the shelf. The
 * family-facing read is the point of the feature, not an afterthought, so most
 * of what is asserted here is the boundary between the two audiences.
 */
function foundItemsYear(): AcademicYear
{
    // The repo's own helper — hand-rolling this fixture got the column names
    // wrong (`starts_on` vs `start_date`) on the first attempt.
    return makeYear(['name' => '2026-2027 Test', 'status' => AcademicYearStatus::Active, 'is_current' => true]);
}

function foundItemsStaff(): User
{
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user->fresh();
}

it('stamps the active year rather than asking for it', function () {
    $year = foundItemsYear();
    $staff = foundItemsStaff();

    $item = app(SaveFoundItemAction::class)->execute(['title' => 'Blue water bottle'], (int) $staff->id);

    expect((int) $item->academic_year_id)->toBe((int) $year->id)
        ->and((int) $item->logged_by)->toBe((int) $staff->id)
        ->and($item->status->value)->toBe('listed')
        // Logged when handed in — defaulting to today is right far more often
        // than it is wrong.
        ->and($item->found_at->toDateString())->toBe(now()->toDateString());
});

it('refuses to file an item when no year is active', function () {
    $staff = foundItemsStaff();

    expect(fn () => app(SaveFoundItemAction::class)->execute(['title' => 'Orphan'], (int) $staff->id))
        ->toThrow(ValidationException::class);
});

it('needs to know what the item is', function () {
    foundItemsYear();
    $staff = foundItemsStaff();

    expect(fn () => app(SaveFoundItemAction::class)->execute(['title' => '   '], (int) $staff->id))
        ->toThrow(ValidationException::class);
});

it('lets any member of staff correct an item, not only the finder', function () {
    // Deliberately unlike E13a materials: the person who finds a bag is often
    // not the person who later learns whose it is.
    foundItemsYear();
    $finder = foundItemsStaff();
    $colleague = foundItemsStaff();

    $item = app(SaveFoundItemAction::class)->execute(['title' => 'Bag'], (int) $finder->id);
    $edited = app(SaveFoundItemAction::class)->execute(
        ['title' => 'Bag', 'description' => 'Grade 5 name tape inside'],
        (int) $colleague->id,
        $item,
    );

    expect($edited->description)->toBe('Grade 5 name tape inside')
        // Editing must not reassign authorship.
        ->and((int) $edited->logged_by)->toBe((int) $finder->id);
});

it('records who released an item and to whom, and refuses a second return', function () {
    foundItemsYear();
    $staff = foundItemsStaff();
    $item = app(SaveFoundItemAction::class)->execute(['title' => 'Jumper'], (int) $staff->id);

    $returned = app(ReturnFoundItemAction::class)->execute($item, (int) $staff->id, "Aishath's mother");

    expect($returned->status->value)->toBe('returned')
        ->and($returned->returned_to)->toBe("Aishath's mother")
        ->and((int) $returned->returned_by)->toBe((int) $staff->id)
        ->and($returned->returned_at)->not->toBeNull();

    // Two people believing they collected the same item is worth an error.
    expect(fn () => app(ReturnFoundItemAction::class)->execute($returned->fresh(), (int) $staff->id))
        ->toThrow(ValidationException::class);
});

it('shows families only what is still on the shelf', function () {
    foundItemsYear();
    $staff = foundItemsStaff();
    $kept = app(SaveFoundItemAction::class)->execute(['title' => 'Lunch box'], (int) $staff->id);
    $gone = app(SaveFoundItemAction::class)->execute(['title' => 'Cap'], (int) $staff->id);
    app(ReturnFoundItemAction::class)->execute($gone, (int) $staff->id);

    $familyView = app(ListFoundItemsAction::class)->execute([], stillHereOnly: true);
    $staffView = app(ListFoundItemsAction::class)->execute();

    expect($familyView->pluck('title')->all())->toBe(['Lunch box'])
        // Staff still need to answer "was it collected?".
        ->and($staffView->pluck('title')->sort()->values()->all())->toBe(['Cap', 'Lunch box'])
        ->and((int) $kept->id)->toBeGreaterThan(0);
});

it('does not mix one school year into another', function () {
    $thisYear = foundItemsYear();
    $staff = foundItemsStaff();
    app(SaveFoundItemAction::class)->execute(['title' => 'This year'], (int) $staff->id);

    // Last year's shelf, filed against a closed year.
    $lastYear = makeYear([
        'name' => '2025-2026', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
        'status' => AcademicYearStatus::Closed,
    ]);
    FoundItem::query()->create([
        'academic_year_id' => $lastYear->id, 'logged_by' => $staff->id,
        'title' => 'Last year', 'found_at' => '2025-09-01', 'status' => 'listed',
    ]);

    expect(app(ListFoundItemsAction::class)->execute()->pluck('title')->all())->toBe(['This year'])
        ->and(app(ListFoundItemsAction::class)->stillHereCount())->toBe(1)
        ->and((int) $thisYear->id)->toBeGreaterThan(0);
});

it('walks the staff screen and the family screen over http', function () {
    foundItemsYear();
    $staff = foundItemsStaff();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.found-items.store'), ['title' => 'Water bottle', 'location' => 'Hall'])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.found-items.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Academics/FoundItems/Index')->etc());

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.found-items.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // A family sees it without any staff role.
    $family = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($family)
        ->get(route('portal.found-items'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/FoundItems')->has('items', 1)->etc());
});

it('refuses the staff screen to a family account', function () {
    foundItemsYear();
    $family = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($family)
        ->get(route('academics.found-items.index'))
        ->assertForbidden();
});

it('stops serving a photo to families once the item is returned', function () {
    // The narrower of the two reads, and the reason Portal has its own action:
    // a returned item stops being a public notice and becomes the office's
    // record, photo included.
    Storage::fake('local');
    foundItemsYear();
    $staff = foundItemsStaff();

    $item = app(SaveFoundItemAction::class)->execute(
        ['title' => 'Named drink bottle'],
        (int) $staff->id,
        null,
        UploadedFile::fake()->image('bottle.jpg'),
    );
    expect($item->photo_media_id)->not->toBeNull();

    $family = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($family)
        ->get(route('portal.found-items.photo', ['foundItem' => $item->id]))
        ->assertOk();

    app(ReturnFoundItemAction::class)->execute($item->fresh(), (int) $staff->id);

    $this->withoutLocalizationMiddleware()->actingAs($family)
        ->get(route('portal.found-items.photo', ['foundItem' => $item->id]))
        ->assertNotFound();

    // Staff keep it, because it is their record of what was handed back.
    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.found-items.photo', ['foundItem' => $item->id]))
        ->assertOk();
});
