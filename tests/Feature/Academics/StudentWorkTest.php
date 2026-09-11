<?php

use App\Domains\Academics\Actions\HideStudentWorkAction;
use App\Domains\Academics\Actions\ListStudentWorkAction;
use App\Domains\Academics\Actions\ListStudentWorkForGuardianAction;
use App\Domains\Academics\Actions\ReadStudentWorkPhotoAction;
use App\Domains\Academics\Actions\ReassignStudentWorkAction;
use App\Domains\Academics\Actions\SaveStudentWorkAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\StudentWork;
use App\Domains\Academics\Models\StudentWorkReassignment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E21 — student work showcase.
 *
 * **The plan names the failure mode EduPage's own docs record: work sent to
 * the wrong parent.** Most of these tests are about that — that a photo can be
 * moved, that the move takes effect at once for both families, and that the
 * move leaves a record.
 */
function workSetup(): array
{
    Storage::fake('local');
    makeYear(['name' => 'Work year', 'status' => AcademicYearStatus::Active, 'is_current' => true]);

    Role::findOrCreate('admin', 'web');
    $staff = User::factory()->create(['name' => 'Ms Shifa']);
    $staff->assignRole('admin');

    $makeFamily = function (string $first, string $last, string $email) {
        $user = User::factory()->create(['name' => $first.' '.$last]);
        $guardianId = DB::table('parent_guardians')->insertGetId([
            'user_id' => $user->id, 'first_name' => $first, 'last_name' => $last,
            'phone' => '777'.random_int(1000, 9999), 'email' => $email,
            'address' => 'Malé', 'relationship' => 'mother',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['user' => $user, 'guardian_id' => $guardianId];
    };

    $childA = makeStudent(['first_name' => 'Mariyam', 'last_name' => 'Hassan']);
    $childB = makeStudent(['first_name' => 'Aminath', 'last_name' => 'Saeed']);

    $familyA = $makeFamily('Hassan', 'Ali', 'hassan.ali@example.test');
    $familyB = $makeFamily('Saeed', 'Moosa', 'saeed.moosa@example.test');

    foreach ([[$familyA['guardian_id'], $childA->id], [$familyB['guardian_id'], $childB->id]] as [$gid, $sid]) {
        DB::table('guardian_student')->insert([
            'guardian_id' => $gid, 'student_id' => $sid,
            'relationship' => 'mother', 'is_primary' => true, 'can_pickup' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [
        'staff' => $staff->fresh(),
        'childA' => $childA, 'childB' => $childB,
        'familyA' => $familyA['user'], 'familyB' => $familyB['user'],
    ];
}

function aPhoto(): UploadedFile
{
    return UploadedFile::fake()->image('work.jpg', 800, 600);
}

it('refuses to save work with nobody to send it to', function () {
    // Without AI reading the handwritten name, picking the pupil is the only
    // thing between a photo and the wrong parent. It is required, never
    // defaulted.
    ['staff' => $staff] = workSetup();

    expect(fn () => app(SaveStudentWorkAction::class)->execute([], $staff->id, aPhoto()))
        ->toThrow(ValidationException::class);

    expect(StudentWork::query()->count())->toBe(0);
});

it('saves a photo privately and shows it only to the right family', function () {
    ['staff' => $staff, 'childA' => $childA, 'familyA' => $familyA, 'familyB' => $familyB] = workSetup();

    $work = app(SaveStudentWorkAction::class)->execute(
        ['student_id' => $childA->id, 'title' => 'Alphabet practice'],
        $staff->id,
        aPhoto(),
    );

    expect((int) $work->academic_year_id)->toBeGreaterThan(0)
        ->and((int) $work->photo_media_id)->toBeGreaterThan(0);

    // The photo is a private media id, never a public path.
    expect($work->getAttributes())->not->toHaveKey('photo_path');

    $mine = app(ListStudentWorkForGuardianAction::class)->execute([$childA->id]);
    expect($mine)->toHaveCount(1)->and($mine->first()['title'])->toBe('Alphabet practice');

    // The other family sees nothing at all.
    expect(app(ListStudentWorkForGuardianAction::class)->execute([]))->toHaveCount(0);

    // And cannot fetch the image by guessing the id.
    expect(app(ReadStudentWorkPhotoAction::class)->execute($work->id, [$childA->id]))->not->toBeNull();
    expect(app(ReadStudentWorkPhotoAction::class)->execute($work->id, [9999]))->toBeNull();

    // Staff have no such restriction — it is their record.
    expect(app(ReadStudentWorkPhotoAction::class)->execute($work->id))->not->toBeNull();

    unset($familyA, $familyB);
});

it('moves work to the right pupil, and both families change at once', function () {
    // This is the plan's named failure mode, so it is the central test.
    ['staff' => $staff, 'childA' => $childA, 'childB' => $childB] = workSetup();

    $work = app(SaveStudentWorkAction::class)
        ->execute(['student_id' => $childA->id], $staff->id, aPhoto());

    app(ReassignStudentWorkAction::class)->execute($work->id, $childB->id, $staff->id);

    // The first family stops seeing it the moment it moves…
    expect(app(ListStudentWorkForGuardianAction::class)->execute([$childA->id]))->toHaveCount(0)
        // …and the second family starts.
        ->and(app(ListStudentWorkForGuardianAction::class)->execute([$childB->id]))->toHaveCount(1);

    // The photo follows the work, not the old family.
    expect(app(ReadStudentWorkPhotoAction::class)->execute($work->id, [$childA->id]))->toBeNull()
        ->and(app(ReadStudentWorkPhotoAction::class)->execute($work->id, [$childB->id]))->not->toBeNull();
});

it('keeps a record of every move, because somebody will ask who saw it', function () {
    ['staff' => $staff, 'childA' => $childA, 'childB' => $childB] = workSetup();

    $work = app(SaveStudentWorkAction::class)
        ->execute(['student_id' => $childA->id], $staff->id, aPhoto());

    app(ReassignStudentWorkAction::class)->execute($work->id, $childB->id, $staff->id);

    $move = StudentWorkReassignment::query()->firstOrFail();
    expect((int) $move->from_student_id)->toBe((int) $childA->id)
        ->and((int) $move->to_student_id)->toBe((int) $childB->id)
        ->and((int) $move->moved_by)->toBe((int) $staff->id);

    // Staff see how often a photo has bounced — one that has moved twice is
    // worth a second look before anybody trusts it.
    app(ReassignStudentWorkAction::class)->execute($work->id, $childA->id, $staff->id);
    expect(app(ListStudentWorkAction::class)->execute()->first()['times_moved'])->toBe(2);
});

it('refuses a move that goes nowhere', function () {
    ['staff' => $staff, 'childA' => $childA] = workSetup();
    $work = app(SaveStudentWorkAction::class)->execute(['student_id' => $childA->id], $staff->id, aPhoto());

    // A no-op audit row would only make the history harder to read.
    expect(fn () => app(ReassignStudentWorkAction::class)->execute($work->id, $childA->id, $staff->id))
        ->toThrow(ValidationException::class);

    expect(fn () => app(ReassignStudentWorkAction::class)->execute(999999, $childA->id, $staff->id))
        ->toThrow(ValidationException::class);

    expect(StudentWorkReassignment::query()->count())->toBe(0);
});

it('hides a photo from families at once without losing it', function () {
    // The urgent case is a photo that caught another child's work in frame.
    ['staff' => $staff, 'childA' => $childA] = workSetup();
    $work = app(SaveStudentWorkAction::class)->execute(['student_id' => $childA->id], $staff->id, aPhoto());

    app(HideStudentWorkAction::class)->execute($work->id, $staff->id);

    expect(app(ListStudentWorkForGuardianAction::class)->execute([$childA->id]))->toHaveCount(0)
        ->and(app(ReadStudentWorkPhotoAction::class)->execute($work->id, [$childA->id]))->toBeNull()
        // Staff keep both the row and the image.
        ->and(app(ListStudentWorkAction::class)->execute())->toHaveCount(1)
        ->and(app(ListStudentWorkAction::class)->execute()->first()['hidden'])->toBeTrue()
        ->and(app(ReadStudentWorkPhotoAction::class)->execute($work->id))->not->toBeNull();

    expect(fn () => app(HideStudentWorkAction::class)->execute($work->id, $staff->id))
        ->toThrow(ValidationException::class);

    // And the hiding itself can be undone.
    app(HideStudentWorkAction::class)->restore($work->id);
    expect(app(ListStudentWorkForGuardianAction::class)->execute([$childA->id]))->toHaveCount(1);
});

it('walks both screens over http and keeps the staff screen off the family side', function () {
    ['staff' => $staff, 'childA' => $childA, 'childB' => $childB, 'familyA' => $familyA, 'familyB' => $familyB] = workSetup();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.work.store'), [
            'student_id' => $childA->id,
            'photo' => aPhoto(),
            'title' => 'Alphabet practice',
        ])->assertSessionHasNoErrors();

    $work = StudentWork::query()->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.work.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Academics/Work/Index')->has('work', 1)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($familyA)
        ->get(route('portal.work'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Work')->has('work', 1)->etc());

    // The other family gets an empty page, and a 404 on the image rather than
    // a hint that it exists.
    $this->withoutLocalizationMiddleware()->actingAs($familyB)
        ->get(route('portal.work'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('work', 0)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($familyB)
        ->get(route('portal.work.photo', $work->id))->assertNotFound();

    $this->withoutLocalizationMiddleware()->actingAs($familyA)
        ->get(route('portal.work.photo', $work->id))->assertOk();

    // Reassign over http, then the two families swap.
    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.work.reassign', $work->id), ['student_id' => $childB->id])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($familyA)
        ->get(route('portal.work.photo', $work->id))->assertNotFound();
    $this->withoutLocalizationMiddleware()->actingAs($familyB)
        ->get(route('portal.work.photo', $work->id))->assertOk();

    // The staff screen is not a family screen.
    $this->withoutLocalizationMiddleware()->actingAs($familyA)
        ->get(route('academics.work.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($familyA)
        ->post(route('academics.work.reassign', $work->id), ['student_id' => $childA->id])->assertForbidden();
});
