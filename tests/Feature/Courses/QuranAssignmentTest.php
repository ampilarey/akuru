<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use App\Domains\Courses\Components\Quran\Actions\ReviewRecitationAction;
use App\Domains\Courses\Components\Quran\Actions\SubmitRecitationAction;
use App\Domains\Courses\Components\Quran\Models\QuranHifzAssignment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\Surah;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedAssignmentFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Ibrahim']);
    $teacher = makeTeacherRow();
    $teacherUser = User::query()->findOrFail($teacher->user_id);
    $surah = Surah::query()->create([
        'index' => 1,
        'arabic_name' => 'الفاتحة',
        'english_name' => 'Al-Fatihah',
        'transliteration' => 'Al-Fatihah',
        'ayah_count' => 7,
        'revelation_place' => 'Meccan',
        'juz_start' => 1,
        'juz_end' => 1,
        'is_active' => true,
    ]);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Hifz engine course',
        'course_type' => 'hifz',
        'subject_id' => CourseSubject::query()->where('slug', 'hifz')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Hifz offering',
        'slug' => 'hifz-offering',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'academic_year_id' => $year->id,
        'created_by' => $admin->id,
    ]);
    $enrollment = app(EnrollUnifiedStudentInOfferingAction::class)
        ->execute($student->id, $course->id, $offering->id);

    return compact('admin', 'year', 'studentUser', 'student', 'teacher', 'teacherUser', 'surah', 'course', 'offering', 'enrollment');
}

it('lets a teacher assign from hifz enrollment targets and shows it to the student', function () {
    $ctx = seedAssignmentFixture();
    $baa = ArabicLetter::query()->where('key_name', 'baa')->firstOrFail();
    $fatha = ArabicHarakah::query()->where('key_name', 'fatha')->firstOrFail();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.assignments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Teach/QuranAssignments')
            ->has('targets', 1)
            ->where('targets.0.student_name', 'Ibrahim Ali')
            ->has('surahs', 1)
            ->has('reference.letters')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.assignments.store'), [
            'student_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'course_offering_id' => $ctx['offering']->id,
            'academic_year_id' => $ctx['year']->id,
            'assignment_type' => 'letter_haraka_practice',
            'expected_letter_id' => $baa->id,
            'expected_haraka_id' => $fatha->id,
            'due_date' => now()->addWeek()->toDateString(),
            'notes' => 'Practise baa with fatha.',
        ])
        ->assertRedirect();

    $assignment = QuranHifzAssignment::query()->firstOrFail();
    expect($assignment->assignment_type->value)->toBe('letter_haraka_practice')
        ->and((int) $assignment->teacher_id)->toBe($ctx['teacher']->id)
        ->and($assignment->status->value)->toBe('assigned');

    // Bad type rejected.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.assignments.store'), [
            'student_id' => $ctx['student']->id,
            'assignment_type' => 'bogus',
        ])
        ->assertSessionHasErrors('assignment_type');

    // The student sees it on their dashboard.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['studentUser'])
        ->get(route('learn.quran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.assignment_type', 'letter_haraka_practice')
            ->where('assignments.0.status', 'assigned')
        );
});

it('moves an assignment through submit and review outcomes', function () {
    $ctx = seedAssignmentFixture();
    $assignment = QuranHifzAssignment::query()->create([
        'student_id' => $ctx['student']->id,
        'teacher_id' => $ctx['teacher']->id,
        'course_id' => $ctx['course']->id,
        'course_offering_id' => $ctx['offering']->id,
        'academic_year_id' => $ctx['year']->id,
        'surah_id' => $ctx['surah']->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'assignment_type' => 'new_memorization',
        'status' => 'assigned',
    ]);

    // A submission against someone else's assignment is refused.
    $stranger = makeStudent(['first_name' => 'Sara']);
    app(EnrollUnifiedStudentInOfferingAction::class)
        ->execute($stranger->id, $ctx['course']->id, $ctx['offering']->id);
    expect(fn () => app(SubmitRecitationAction::class)->execute([
        'student_id' => $stranger->id,
        'quran_hifz_assignment_id' => $assignment->id,
    ]))->toThrow(ValidationException::class);

    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'quran_hifz_assignment_id' => $assignment->id,
    ]);
    expect($assignment->refresh()->status->value)->toBe('submitted');

    app(ReviewRecitationAction::class)->execute($submission->id, [
        'status' => 'needs_repeat',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['teacherUser']->id,
        'mistakes' => [['ayah_number' => 2, 'mistake_type' => 'missed_word']],
    ]);
    expect($assignment->refresh()->status->value)->toBe('needs_repeat');

    // A repeat submission and a passing review close it out.
    $second = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
        'quran_hifz_assignment_id' => $assignment->id,
    ]);
    expect($assignment->refresh()->status->value)->toBe('submitted');
    app(ReviewRecitationAction::class)->execute($second->id, [
        'status' => 'passed',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['teacherUser']->id,
    ]);
    expect($assignment->refresh()->status->value)->toBe('passed');
});

it('scopes the board, cancels, and exports CSV', function () {
    $ctx = seedAssignmentFixture();
    $assignment = QuranHifzAssignment::query()->create([
        'student_id' => $ctx['student']->id,
        'teacher_id' => $ctx['teacher']->id,
        'assignment_type' => 'revision',
        'status' => 'assigned',
    ]);
    $otherTeacher = makeTeacherRow();
    QuranHifzAssignment::query()->create([
        'student_id' => $ctx['student']->id,
        'teacher_id' => $otherTeacher->id,
        'assignment_type' => 'revision',
        'status' => 'assigned',
    ]);

    // A teacher sees only their own board; staff see all.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.assignments'))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1));
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('teach.assignments'))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 2));

    // Another teacher cannot touch this teacher's assignment.
    $otherUser = User::query()->findOrFail($otherTeacher->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->put(route('teach.assignments.update', $assignment->id), ['status' => 'cancelled'])
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->put(route('teach.assignments.update', $assignment->id), ['status' => 'cancelled'])
        ->assertRedirect();
    expect($assignment->refresh()->status->value)->toBe('cancelled');

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.assignments', ['format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
