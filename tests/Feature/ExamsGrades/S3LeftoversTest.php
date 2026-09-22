<?php

use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * The S3 audit's dead table (STATUS §5ey): the pre-S3 `grades` stub that the
 * spec named on its first line and the cycle was built beside. Zero rows
 * everywhere, two relations nobody called, one morph alias.
 */
it('has dropped the pre-S3 grades table, its model, its alias and its relations', function () {
    expect(Schema::hasTable('grades'))->toBeFalse()
        ->and(Schema::hasTable('term_grades'))->toBeTrue()
        ->and(array_key_exists('grade', config('morph-map')))->toBeFalse()
        ->and(class_exists(\App\Domains\Academics\Models\Grade::class))->toBeFalse()
        ->and(method_exists(Student::class, 'grades'))->toBeFalse()
        ->and(method_exists(Teacher::class, 'grades'))->toBeFalse();
});
