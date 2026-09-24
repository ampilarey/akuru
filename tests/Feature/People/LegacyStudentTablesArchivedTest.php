<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * S1 Deploy 3, slice 3 (STATUS §5gh): the legacy student tables are archived,
 * not dropped (S1_SPEC), and nothing constrains the live tables to them.
 */
it('keeps the legacy registration rows and guardian links as archives', function () {
    expect(Schema::hasTable('registration_students'))->toBeFalse()
        ->and(Schema::hasTable('student_guardians'))->toBeFalse()
        ->and(Schema::hasTable('archived_registration_students'))->toBeTrue()
        ->and(Schema::hasTable('archived_student_guardians'))->toBeTrue()
        // Each archived row still says which student it became.
        ->and(Schema::hasColumn('archived_registration_students', 'unified_student_id'))->toBeTrue()
        ->and(Schema::hasColumn('students', 'legacy_registration_student_id'))->toBeFalse();
});

it('keeps the old pointers on enrolments and payments as plain history, unconstrained', function () {
    foreach (['course_enrollments', 'payments'] as $table) {
        $pointsAtArchive = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $fk) => in_array($fk['foreign_table'], ['archived_registration_students', 'registration_students'], true));

        expect(Schema::hasColumn($table, 'student_id'))->toBeFalse("{$table}.student_id should be archived")
            ->and(Schema::hasColumn($table, 'archived_registration_student_id'))->toBeTrue()
            ->and($pointsAtArchive)->toBeFalse("{$table} still has a foreign key into the archive");
    }
});
