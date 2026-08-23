<?php

use App\Domains\Identity\Models\User;
use App\Domains\Media\Enums\DocumentType;
use App\Domains\Media\Models\Document;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\StudentStatusHistory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('changes status only via ChangeStudentStatusAction and writes history', function () {
    $actor = User::factory()->create();
    $student = makeStudent();

    expect($student->status)->toBe(StudentStatus::Active)
        ->and(in_array('status', $student->getFillable(), true))->toBeFalse();

    $updated = app(ChangeStudentStatusAction::class)->execute(
        $student,
        StudentStatus::Withdrawn,
        $actor->id,
        'left the island',
        '2026-08-23',
    );

    expect($updated->status)->toBe(StudentStatus::Withdrawn);

    $history = StudentStatusHistory::query()->where('student_id', $student->id)->sole();
    expect($history->from_status)->toBe(StudentStatus::Active)
        ->and($history->to_status)->toBe(StudentStatus::Withdrawn)
        ->and($history->reason)->toBe('left the island')
        ->and($history->changed_by)->toBe($actor->id)
        ->and($history->effective_date->toDateString())->toBe('2026-08-23');
});

it('ignores mass-assignment of status', function () {
    $student = makeStudent();

    $student->fill(['status' => StudentStatus::Withdrawn->value])->save();
    $student->refresh();

    expect($student->status)->toBe(StudentStatus::Active)
        ->and(StudentStatusHistory::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('creates a course-only student with null school fields', function () {
    $student = makeStudent([
        'school_id' => null,
        'class_id' => null,
        'student_id' => null,
        'admission_date' => null,
    ]);

    expect($student->school_id)->toBeNull()
        ->and($student->class_id)->toBeNull()
        ->and($student->student_id)->toBeNull()
        ->and($student->admission_date)->toBeNull()
        ->and($student->nationality)->toBe('MV');
});

it('enforces guardian_student unique guardian_id plus student_id', function () {
    $student = makeStudent();
    $guardian = makeGuardian();

    $student->guardians()->attach($guardian->id, [
        'relationship' => GuardianRelationship::Father->value,
        'is_primary' => true,
        'can_pickup' => true,
        'financial_responsible' => false,
    ]);

    expect(fn () => $student->guardians()->attach($guardian->id, [
        'relationship' => GuardianRelationship::Guardian->value,
        'is_primary' => false,
        'can_pickup' => true,
        'financial_responsible' => false,
    ]))->toThrow(QueryException::class);
});

it('stores documents.documentable_type as a morph alias not a FQCN', function () {
    $actor = User::factory()->create();
    $student = makeStudent();

    $document = new Document([
        'media_path' => 'docs/birth.pdf',
        'document_type' => DocumentType::BirthCertificate,
        'title' => 'Birth certificate',
        'uploaded_by' => $actor->id,
    ]);
    $document->documentable()->associate($student);
    $document->save();

    $stored = DB::table('documents')->where('id', $document->id)->value('documentable_type');

    expect($stored)->toBe('student')
        ->and($stored)->not->toContain('\\')
        ->and($student->getMorphClass())->toBe('student')
        ->and($document->fresh()->documentable)->toBeInstanceOf(Student::class);
});

it('adds course_enrollments.unified_student_id without renaming student_id', function () {
    expect(Schema::hasColumn('course_enrollments', 'student_id'))->toBeTrue()
        ->and(Schema::hasColumn('course_enrollments', 'unified_student_id'))->toBeTrue();
});
