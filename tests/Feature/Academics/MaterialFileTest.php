<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\AttachFileToMaterialAction;
use App\Domains\Academics\Actions\AttachMaterialsToLessonAction;
use App\Domains\Academics\Actions\ListHomeworkForStudentAction;
use App\Domains\Academics\Actions\RemoveMaterialFileAction;
use App\Domains\Academics\Actions\SaveTeachingMaterialAction;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\TeachingMaterialFile;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E13c — a material can carry files.
 *
 * The security surface is the point of this slice: the files are private, and
 * the download route is reachable by families. Most of what follows is about
 * who is refused.
 */
function materialWithFile(?User $author = null, string $name = 'worksheet.pdf'): array
{
    $author ??= User::factory()->create();
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Alphabet worksheet'], (int) $author->id);
    $file = app(AttachFileToMaterialAction::class)->execute(
        $material,
        UploadedFile::fake()->create($name, 12, 'application/pdf'),
        (int) $author->id,
    );

    return ['author' => $author, 'material' => $material, 'file' => $file];
}

function registerStaff(): User
{
    $user = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $user->givePermissionTo('registers.fill');

    return $user;
}

it('stores a file against the material with its own metadata', function () {
    $seed = materialWithFile();

    // The name, mime and size are copied at upload time so listing a material
    // never reads Media's table (rule 3).
    expect($seed['file']->original_name)->toBe('worksheet.pdf')
        ->and($seed['file']->mime)->toBe('application/pdf')
        ->and($seed['file']->size)->toBeGreaterThan(0)
        ->and($seed['file']->media_file_id)->toBeGreaterThan(0)
        ->and((int) $seed['file']->uploaded_by)->toBe((int) $seed['author']->id);
});

it('refuses a file on a material somebody else wrote', function () {
    $material = app(SaveTeachingMaterialAction::class)
        ->execute(['title' => 'Mine'], (int) User::factory()->create()->id);

    app(AttachFileToMaterialAction::class)->execute(
        $material,
        UploadedFile::fake()->create('theirs.pdf', 4, 'application/pdf'),
        (int) User::factory()->create()->id,
    );
})->throws(ValidationException::class);

it('refuses a file type a teacher would not hand out', function () {
    $author = User::factory()->create();
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Mine'], (int) $author->id);

    app(AttachFileToMaterialAction::class)->execute(
        $material,
        UploadedFile::fake()->create('payload.php', 2, 'application/x-httpd-php'),
        (int) $author->id,
    );
})->throws(ValidationException::class);

it('refuses a file over the size cap', function () {
    $author = User::factory()->create();
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Mine'], (int) $author->id);

    app(AttachFileToMaterialAction::class)->execute(
        $material,
        UploadedFile::fake()->create('huge.pdf', 21 * 1024, 'application/pdf'),
        (int) $author->id,
    );
})->throws(ValidationException::class);

it('removes the link without deleting another domains file', function () {
    $seed = materialWithFile();
    $mediaId = (int) $seed['file']->media_file_id;

    app(RemoveMaterialFileAction::class)->execute($seed['file'], (int) $seed['author']->id);

    // Media owns its own lifecycle: a domain reaching across to delete another's
    // bytes is how a file still referenced elsewhere disappears.
    expect(TeachingMaterialFile::query()->count())->toBe(0)
        ->and(\App\Domains\Media\Models\MediaFile::query()->whereKey($mediaId)->exists())->toBeTrue();
});

it('refuses a removal by someone who did not write the material', function () {
    $seed = materialWithFile();

    app(RemoveMaterialFileAction::class)->execute($seed['file'], (int) User::factory()->create()->id);
})->throws(ValidationException::class);

it('lets any register staff download any material file', function () {
    $seed = materialWithFile();

    $this->withoutLocalizationMiddleware()
        ->actingAs(registerStaff())
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('refuses an account with no pupil and no register permission', function () {
    $seed = materialWithFile();

    // Logged in is not entitled. Without this the route hands every uploaded
    // worksheet to anyone with an account.
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertForbidden();
});

it('refuses an anonymous visitor', function () {
    $seed = materialWithFile();

    $this->withoutLocalizationMiddleware()
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertRedirect();
});

/**
 * A pupil on a class, plus a lesson log carrying the material.
 */
function familyFileSeed(bool $sendHome, string $status = LessonLogStatus::Submitted->value): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $log = makeLessonLog([
        'year' => $year,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'homework' => 'Read page 12',
        'status' => $status,
        'submitted_at' => now(),
    ]);

    $teacherUser = User::query()->findOrFail($log->teacher->user_id);
    $seed = materialWithFile($teacherUser);

    app(AttachMaterialsToLessonAction::class)->execute(
        $log,
        [(int) $seed['material']->id],
        (int) $teacherUser->id,
        false,
        $sendHome ? [(int) $seed['material']->id] : [],
    );

    return [...$seed, 'student' => $student, 'log' => $log, 'class' => $class];
}

it('lets a pupil download a file sent home with their homework', function () {
    $seed = familyFileSeed(true);

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($seed['student']->user_id))
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertOk();
});

it('refuses a pupil a file the teacher did not send home', function () {
    $seed = familyFileSeed(false);

    // Attaching a material to a lesson is deliberately not enough. E13b's
    // distinction is enforced again here so the download route cannot become a
    // way around it.
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($seed['student']->user_id))
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertForbidden();
});

it('refuses a pupil a file on a register still in draft', function () {
    $seed = familyFileSeed(true, LessonLogStatus::Draft->value);

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($seed['student']->user_id))
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertForbidden();
});

it('refuses a pupil from another class entirely', function () {
    $seed = familyFileSeed(true);
    $outsider = makeStudent();

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($outsider->user_id))
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertForbidden();
});

it('lets a guardian download what their child was sent', function () {
    $seed = familyFileSeed(true);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($seed['student'], $guardian, 'father');

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($guardian->user_id))
        ->get(route('academics.materials.files.show', $seed['file']))
        ->assertOk();
});

it('lists the files on the homework a family reads', function () {
    $seed = familyFileSeed(true);

    $row = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id)->firstOrFail();

    expect($row['materials'][0]['files'])->toHaveCount(1)
        ->and($row['materials'][0]['files'][0]['name'])->toBe('worksheet.pdf');
});

it('uploads and removes over http', function () {
    $staff = registerStaff();
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Alphabet'], (int) $staff->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->post(route('academics.materials.files.store', $material), [
            'file' => UploadedFile::fake()->create('handout.pdf', 8, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('academics.materials.index'));

    $file = TeachingMaterialFile::query()->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->delete(route('academics.materials.files.destroy', $file))
        ->assertRedirect();

    expect(TeachingMaterialFile::query()->count())->toBe(0);
});
