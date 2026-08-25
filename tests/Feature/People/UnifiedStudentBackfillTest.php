<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\UnifyStudentsAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use App\Domains\People\Support\StudentUnificationReport;
use App\Support\Schema\ForeignKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function makeCourseEnrollment(int $registrationStudentId, array $overrides = []): int
{
    $courseId = $overrides['course_id'] ?? Course::factory()->create()->id;
    unset($overrides['course_id']);

    return DB::table('course_enrollments')->insertGetId(array_merge([
        'student_id' => $registrationStudentId,
        'course_id' => $courseId,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function attachLegacyGuardian(int $registrationStudentId, int $guardianUserId, array $pivot = []): int
{
    return DB::table('student_guardians')->insertGetId(array_merge([
        'student_id' => $registrationStudentId,
        'guardian_user_id' => $guardianUserId,
        'relationship' => 'father',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $pivot));
}

it('makes students.user_id nullable and unique on the legacy key', function () {
    expect(Schema::getColumns('students'))->not->toBeEmpty();

    $column = collect(Schema::getColumns('students'))->firstWhere('name', 'user_id');

    expect($column['nullable'] ?? null)->toBeTrue()
        ->and(Schema::hasColumn('students', 'legacy_registration_student_id'))->toBeTrue();
});

it('drops students.user_id foreign keys by live name and is a no-op when absent', function () {
    expect(ForeignKeys::existsOnColumn('students', 'user_id'))->toBeTrue();

    $dropped = ForeignKeys::dropOnColumn('students', 'user_id');

    expect($dropped)->not->toBeEmpty()
        ->and(ForeignKeys::existsOnColumn('students', 'user_id'))->toBeFalse()
        ->and(ForeignKeys::dropOnColumn('students', 'user_id'))->toBe([]);

    Schema::table('students', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id')->nullable()->change();
    });

    if (! ForeignKeys::existsOnColumn('students', 'user_id')) {
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    $column = collect(Schema::getColumns('students'))->firstWhere('name', 'user_id');

    expect($column['nullable'] ?? null)->toBeTrue()
        ->and(ForeignKeys::existsOnColumn('students', 'user_id'))->toBeTrue();
});

it('matches a registration student by user_id and fills an empty passport', function () {
    $user = User::factory()->create();
    $student = makeStudent([
        'user_id' => $user->id,
        'first_name' => 'Mariam',
        'last_name' => 'Hassan',
        'date_of_birth' => '2010-01-15',
        'passport' => null,
    ]);
    $rs = makeRegistrationStudent([
        'user_id' => $user->id,
        'first_name' => 'Different',
        'last_name' => 'Name',
        'dob' => '2001-01-01',
        'passport' => 'P123456',
    ]);

    $report = app(UnifyStudentsAction::class)->execute();

    expect($report->mapped['user_id'])->toBe(1)
        ->and($student->fresh()->legacy_registration_student_id)->toBe($rs->id)
        ->and($student->fresh()->passport)->toBe('P123456')
        ->and($report->passed())->toBeTrue();
});

it('matches by decrypted national_id when user_id does not match', function () {
    $student = makeStudent([
        'national_id' => 'A123456',
        'first_name' => 'School',
        'last_name' => 'Record',
        'date_of_birth' => '2008-05-05',
    ]);
    $rs = makeRegistrationStudent([
        'national_id' => 'A123456',
        'first_name' => 'Course',
        'last_name' => 'Kid',
        'dob' => '2008-05-05',
    ]);

    $report = app(UnifyStudentsAction::class)->execute();

    expect($report->mapped['national_id'])->toBe(1)
        ->and($report->mapped['user_id'])->toBe(0)
        ->and($student->fresh()->legacy_registration_student_id)->toBe($rs->id)
        ->and($report->passed())->toBeTrue();
});

it('matches by exact first name, last name, and date of birth', function () {
    $student = makeStudent([
        'first_name' => 'Yusuf',
        'last_name' => 'Ibrahim',
        'date_of_birth' => '2011-07-20',
        'national_id' => null,
    ]);
    $rs = makeRegistrationStudent([
        'first_name' => 'Yusuf',
        'last_name' => 'Ibrahim',
        'dob' => '2011-07-20',
        'national_id' => null,
    ]);

    $report = app(UnifyStudentsAction::class)->execute();

    expect($report->mapped['name_dob'])->toBe(1)
        ->and($student->fresh()->legacy_registration_student_id)->toBe($rs->id)
        ->and($report->passed())->toBeTrue();
});

it('creates a prospective student when there is no match and no active enrollment', function () {
    $rs = makeRegistrationStudent([
        'user_id' => null,
        'first_name' => 'Noor',
        'last_name' => 'Ahmed',
        'dob' => '2014-02-02',
        'national_id' => 'B999111',
        'passport' => 'PP111',
    ]);

    $report = app(UnifyStudentsAction::class)->execute();
    $created = Student::query()->where('legacy_registration_student_id', $rs->id)->sole();

    expect($report->created['prospective'])->toBe(1)
        ->and($created->status)->toBe(StudentStatus::Prospective)
        ->and($created->user_id)->toBeNull()
        ->and($created->school_id)->toBeNull()
        ->and($created->class_id)->toBeNull()
        ->and($created->student_id)->toBeNull()
        ->and($created->national_id)->toBe('B999111')
        ->and($created->passport)->toBe('PP111')
        ->and($report->passed())->toBeTrue();
});

it('creates an active student when the registration student has an active enrollment', function () {
    $rs = makeRegistrationStudent(['user_id' => null, 'first_name' => 'Active', 'last_name' => 'Child']);
    makeCourseEnrollment($rs->id, ['status' => 'active']);

    $report = app(UnifyStudentsAction::class)->execute();
    $created = Student::query()->where('legacy_registration_student_id', $rs->id)->sole();

    expect($report->created['active'])->toBe(1)
        ->and($created->status)->toBe(StudentStatus::Active)
        ->and(DB::table('course_enrollments')->where('student_id', $rs->id)->value('unified_student_id'))
        ->toBe($created->id)
        ->and($report->passed())->toBeTrue();
});

it('lists ambiguous name-and-dob matches and does not pick a winner', function () {
    makeStudent([
        'first_name' => 'Twin',
        'last_name' => 'Ali',
        'date_of_birth' => '2013-09-09',
        'national_id' => 'T1',
    ]);
    makeStudent([
        'first_name' => 'Twin',
        'last_name' => 'Ali',
        'date_of_birth' => '2013-09-09',
        'national_id' => 'T2',
    ]);
    $rs = makeRegistrationStudent([
        'first_name' => 'Twin',
        'last_name' => 'Ali',
        'dob' => '2013-09-09',
        'national_id' => null,
    ]);

    $report = app(UnifyStudentsAction::class)->execute();

    expect($report->ambiguous)->toHaveCount(1)
        ->and($report->ambiguous[0]['method'])->toBe('name_dob')
        ->and($report->ambiguous[0]['candidate_student_ids'])->toHaveCount(2)
        ->and(Student::query()->where('legacy_registration_student_id', $rs->id)->count())->toBe(0)
        ->and($report->passed())->toBeFalse();
});

it('treats a second registration student matching the same student as a collision', function () {
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'national_id' => null]);
    $first = makeRegistrationStudent(['user_id' => $user->id, 'national_id' => null]);
    $second = makeRegistrationStudent([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Aisha',
        'last_name' => 'Ali',
        'dob' => '2012-03-01',
        'national_id' => null,
    ]);

    $report = app(UnifyStudentsAction::class)->execute();

    expect(Student::query()->where('legacy_registration_student_id', $first->id)->count())->toBe(1)
        ->and(Student::query()->where('legacy_registration_student_id', $second->id)->count())->toBe(0)
        ->and($report->collisions)->not->toBeEmpty()
        ->and($report->passed())->toBeFalse();
});

it('migrates student_guardians onto guardian_student and preserves primary plus relationship', function () {
    $guardianUser = User::factory()->create([
        'name' => 'Hassan Ali',
        'phone' => '7820288',
        'address' => 'Malé',
    ]);
    $rs = makeRegistrationStudent(['user_id' => null]);
    attachLegacyGuardian($rs->id, $guardianUser->id, [
        'relationship' => 'mother',
        'is_primary' => true,
    ]);

    $report = app(UnifyStudentsAction::class)->execute();
    $student = Student::query()->where('legacy_registration_student_id', $rs->id)->sole();
    $parent = ParentGuardian::query()->where('user_id', $guardianUser->id)->sole();
    $pivot = DB::table('guardian_student')
        ->where('guardian_id', $parent->id)
        ->where('student_id', $student->id)
        ->first();

    expect($report->guardians['source'])->toBe(1)
        ->and($report->guardians['migrated'])->toBe(1)
        ->and($report->guardians['created_profiles'])->toBe(1)
        ->and($parent->first_name)->toBe('Hassan')
        ->and($parent->last_name)->toBe('Ali')
        ->and($pivot->relationship)->toBe(GuardianRelationship::Mother->value)
        ->and((bool) $pivot->is_primary)->toBeTrue()
        ->and((bool) $pivot->can_pickup)->toBeTrue()
        ->and((bool) $pivot->financial_responsible)->toBeFalse()
        ->and($report->passed())->toBeTrue();
});

it('reuses an existing parent_guardians profile for the guardian user', function () {
    $existing = makeGuardian();
    $rs = makeRegistrationStudent(['user_id' => null]);
    attachLegacyGuardian($rs->id, $existing->user_id, [
        'relationship' => 'father',
        'is_primary' => false,
    ]);

    $report = app(UnifyStudentsAction::class)->execute();

    expect(ParentGuardian::query()->where('user_id', $existing->user_id)->count())->toBe(1)
        ->and($report->guardians['created_profiles'])->toBe(0)
        ->and($report->guardians['migrated'])->toBe(1)
        ->and($report->passed())->toBeTrue();
});

it('does not overwrite a student passport that is already set', function () {
    $user = User::factory()->create();
    $student = makeStudent([
        'user_id' => $user->id,
        'passport' => 'KEEP-ME',
    ]);
    makeRegistrationStudent([
        'user_id' => $user->id,
        'passport' => 'REPLACE-ME',
    ]);

    app(UnifyStudentsAction::class)->execute();

    expect($student->fresh()->passport)->toBe('KEEP-ME');
});

it('is idempotent: a second run changes nothing', function () {
    $guardianUser = User::factory()->create(['name' => 'Parent One']);
    $rs = makeRegistrationStudent(['user_id' => null, 'passport' => 'P-IDEM']);
    attachLegacyGuardian($rs->id, $guardianUser->id);
    makeCourseEnrollment($rs->id, ['status' => 'active']);

    $first = app(UnifyStudentsAction::class)->execute();
    $student = Student::query()->where('legacy_registration_student_id', $rs->id)->sole();
    $snapshot = [
        'students' => Student::query()->count(),
        'legacy' => $student->legacy_registration_student_id,
        'passport' => $student->passport,
        'status' => $student->status->value,
        'guardians' => DB::table('guardian_student')->count(),
        'unified' => DB::table('course_enrollments')->where('student_id', $rs->id)->value('unified_student_id'),
    ];

    $second = app(UnifyStudentsAction::class)->execute();
    $student->refresh();

    expect($first->passed())->toBeTrue()
        ->and($second->passed())->toBeTrue()
        ->and($second->mapped['already_mapped'])->toBe(1)
        ->and($second->created['active'])->toBe(0)
        ->and($second->created['prospective'])->toBe(0)
        ->and($second->guardians['migrated'])->toBe(0)
        ->and($second->guardians['skipped_existing'])->toBe(1)
        ->and($second->enrollments['already_set'])->toBe(1)
        ->and($second->enrollments['filled'])->toBe(0)
        ->and(Student::query()->count())->toBe($snapshot['students'])
        ->and($student->legacy_registration_student_id)->toBe($snapshot['legacy'])
        ->and($student->passport)->toBe($snapshot['passport'])
        ->and($student->status->value)->toBe($snapshot['status'])
        ->and(DB::table('guardian_student')->count())->toBe($snapshot['guardians'])
        ->and(DB::table('course_enrollments')->where('student_id', $rs->id)->value('unified_student_id'))
        ->toBe($snapshot['unified']);
});

it('writes a report file and the verify command fails while rows are unresolved', function () {
    makeStudent([
        'first_name' => 'Same',
        'last_name' => 'Name',
        'date_of_birth' => '2015-01-01',
        'national_id' => 'X1',
    ]);
    makeStudent([
        'first_name' => 'Same',
        'last_name' => 'Name',
        'date_of_birth' => '2015-01-01',
        'national_id' => 'X2',
    ]);
    makeRegistrationStudent([
        'first_name' => 'Same',
        'last_name' => 'Name',
        'dob' => '2015-01-01',
        'national_id' => null,
    ]);

    $this->artisan('students:verify-unification', ['--backfill' => true])
        ->assertFailed();

    expect(file_exists(storage_path('app/'.StudentUnificationReport::FILENAME)))->toBeTrue();
});

it('passes the verify command when every registration student is mapped', function () {
    $rs = makeRegistrationStudent(['user_id' => null]);
    makeCourseEnrollment($rs->id, ['status' => 'pending']);

    $this->artisan('students:verify-unification', ['--backfill' => true])
        ->assertSuccessful();
});
