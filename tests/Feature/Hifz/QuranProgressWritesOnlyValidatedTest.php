<?php

use App\Domains\Hifz\Models\QuranProgress;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `QuranProgressController` validated every field its form uses and then wrote
 * `$request->all()`.
 *
 * The gap is the difference between the two lists: `QuranProgress::$fillable`
 * carries `date_completed`, `last_revision_date` and `revision_count`, and the
 * validation mentions none of them — so a caller could set a completion date
 * and a revision count the screen never offers. Staff-only, and small, which is
 * why it is filed as hygiene rather than a severity.
 *
 * It is worth closing because it does not stay small on its own: the next
 * column added to this model becomes writable here the moment it is added, and
 * nothing would say so.
 *
 * CLAUDE.md rule 7's freeze has expired by its own terms (§2b, ADR-029), so
 * this is ordinary code. It is a correctness fix and not a refactor: the
 * screens, routes and behaviour are untouched.
 */
function hifzTeacher(): User
{
    Role::findOrCreate('teacher', 'web');
    $user = User::factory()->create();
    $user->assignRole('teacher');

    return $user->fresh();
}

it('ignores columns the form never validated', function () {
    $student = makeStudent();
    $teacher = makeTeacherRow();

    $this->withoutLocalizationMiddleware()->actingAs(hifzTeacher())
        ->post('/quran-progress', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'surah_name' => 'Al-Fatihah',
            'surah_name_arabic' => 'الفاتحة',
            'surah_number' => 1,
            'type' => 'memorization',
            'status' => 'in_progress',
            // None of these are in the validation rules, and all three are
            // `$fillable`. They used to land.
            'revision_count' => 99,
            'date_completed' => '2020-01-01',
            'last_revision_date' => '2020-01-01',
        ]);

    $row = QuranProgress::query()->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->surah_name)->toBe('Al-Fatihah')
        ->and((int) $row->revision_count)->not->toBe(99)
        ->and($row->date_completed)->toBeNull();
});

it('still records what the form does send', function () {
    // Without this the fix could be "write nothing", which passes the case
    // above and breaks the screen.
    $student = makeStudent();
    $teacher = makeTeacherRow();

    $this->withoutLocalizationMiddleware()->actingAs(hifzTeacher())
        ->post('/quran-progress', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'surah_name' => 'Al-Baqarah',
            'surah_name_arabic' => 'البقرة',
            'surah_number' => 2,
            'from_ayah' => 1,
            'to_ayah' => 20,
            'type' => 'revision',
            'status' => 'completed',
            'accuracy_percentage' => 90,
            'teacher_notes' => 'Good pace.',
        ]);

    $row = QuranProgress::query()->latest('id')->first();

    expect($row->surah_name)->toBe('Al-Baqarah')
        ->and((int) $row->surah_number)->toBe(2)
        ->and((int) $row->accuracy_percentage)->toBe(90)
        ->and($row->teacher_notes)->toBe('Good pace.')
        ->and((int) $row->student_id)->toBe((int) $student->id);
});
