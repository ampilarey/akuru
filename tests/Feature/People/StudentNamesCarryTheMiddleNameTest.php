<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Courses\Actions\ListCertificateIssueOptionsAction;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Student;
use App\Support\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * C17 slice R4b (STATUS §5op). Slice R4 gave a student a middle name on the
 * registration forms; the lists, reports and CSVs went on joining the first
 * and the last. These hold that a student is called by the whole name
 * everywhere, that the office can type one, and that no new join leaves it out.
 */
uses(RefreshDatabase::class);

it('joins a name from its parts, leaving out the empty ones', function () {
    expect(PersonName::join('Aisha', 'Mohamed', 'Ali'))->toBe('Aisha Mohamed Ali')
        ->and(PersonName::join('Aisha', null, 'Ali'))->toBe('Aisha Ali')
        ->and(PersonName::join(' Aisha ', '  ', 'Ali'))->toBe('Aisha Ali')
        ->and(PersonName::ofStudent((object) ['first_name' => 'Aisha', 'middle_name' => 'Mohamed', 'last_name' => 'Ali']))->toBe('Aisha Mohamed Ali')
        ->and(PersonName::ofStudent(['first_name' => 'Aisha', 'last_name' => 'Ali']))->toBe('Aisha Ali')
        ->and(PersonName::ofStudent(null))->toBe('');
});

it('lets the office type a middle name, and lists, finds and exports the student by it', function () {
    $admin = actingPeopleAdmin();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('people.students.store'), [
            'first_name' => 'Zunaira',
            'middle_name' => 'Hassan',
            'last_name' => 'Qasim',
            'date_of_birth' => '2013-04-04',
            'gender' => 'female',
            'status' => StudentStatus::Active->value,
        ])
        ->assertRedirect();
    $student = Student::query()->where('first_name', 'Zunaira')->sole();
    expect($student->middle_name)->toBe('Hassan')
        ->and($student->full_name)->toBe('Zunaira Hassan Qasim');

    foreach (['Hassan', 'Zunaira Hassan Qasim', 'Zunaira Qasim'] as $search) {
        $this->withoutLocalizationMiddleware()->actingAs($admin)
            ->get(route('people.students.index', ['search' => $search]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('students', 1)->where('students.0.middle_name', 'Hassan'));
    }

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('people.students.show', $student))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('student.middle_name', 'Hassan'));

    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('people.students.export'));
    expect($csv->streamedContent())->toContain('first_name,middle_name,last_name')
        ->toContain('Zunaira,Hassan,Qasim');

    // Emptied on an edit, it is no middle name — not an empty string.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('people.students.update', $student), [
            'first_name' => 'Zunaira',
            'middle_name' => '',
            'last_name' => 'Qasim',
            'date_of_birth' => '2013-04-04',
            'gender' => 'female',
            'status' => StudentStatus::Active->value,
        ])
        ->assertRedirect();
    expect($student->fresh()->middle_name)->toBeNull();
});

it('carries the middle name into the lists other screens build', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'A');
    $student = makeStudent(['first_name' => 'Aisha', 'middle_name' => 'Mohamed', 'last_name' => 'Ali']);
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    expect(app(ListClassRosterAction::class)->execute((int) $class->id)->first()['name'])->toBe('Aisha Mohamed Ali')
        ->and(app(ListStudentsByIdsAction::class)->execute([(int) $student->id])->first()['name'])->toBe('Aisha Mohamed Ali')
        ->and(collect(app(ListCertificateIssueOptionsAction::class)->execute()['students'])->firstWhere('id', $student->id)['name'])->toBe('Aisha Mohamed Ali')
        ->and(app(ResolveStudentForUserAction::class)->execute((int) $student->user_id)['middle_name'])->toBe('Mohamed');

    // A parent's children, the confirmed and the waiting.
    $guardian = makeGuardian();
    $waiting = makeStudent(['first_name' => 'Hawwa', 'middle_name' => 'Ibrahim', 'last_name' => 'Ali']);
    DB::table('guardian_student')->insert([
        ['guardian_id' => $guardian->id, 'student_id' => $student->id, 'relationship' => 'father', 'is_primary' => true, 'verification_status' => 'verified', 'created_at' => now(), 'updated_at' => now()],
        ['guardian_id' => $guardian->id, 'student_id' => $waiting->id, 'relationship' => 'father', 'is_primary' => false, 'verification_status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $children = app(ListGuardianChildrenAction::class);
    $parent = User::query()->findOrFail($guardian->user_id);
    expect(PersonName::ofStudent($children->executeForGuardianUserId((int) $parent->id)->first()))->toBe('Aisha Mohamed Ali')
        ->and(PersonName::ofStudent($children->executePendingForGuardianUserId((int) $parent->id)->first()))->toBe('Hawwa Ibrahim Ali');
});

it('builds no student name from the first and last alone', function () {
    // A join of a student's (or a child's, or a learner's) first and last
    // name that does not go through PersonName and does not name the middle
    // one. Staff, teachers and guardians have no middle name column.
    $pattern = '/\$(student|child|c|pupil|learner|self|known|existingStudent|candidate|students\[[^\]]*\])\b[^;]*first_name[^;]*last_name/';
    $found = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (file($file->getPathname()) as $n => $line) {
            if (preg_match($pattern, $line) && ! str_contains($line, 'middle_name') && ! str_contains($line, 'PersonName')) {
                $found[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($n + 1);
            }
        }
    }

    expect($found)->toBe([], 'Student names joined without the middle name: '.implode(', ', $found));
});
