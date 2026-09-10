<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;

/**
 * A class with a teacher on its timetable and families behind its pupils.
 *
 * Shared rather than declared in one test file: E2b's action tests and its HTTP
 * walk both need it, and a function declared in a test file only exists if that
 * file happens to have been loaded.
 *
 * @param  int  $pupils  how many children are on the roster
 * @param  bool  $withGuardians  false leaves the pupils with no guardian account
 * @return array<string, mixed>
 */
function seedClassWithFamilies(int $pupils = 3, bool $withGuardians = true): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();

    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    $students = [];
    $guardians = [];
    for ($i = 0; $i < $pupils; $i++) {
        $student = makeStudent();
        app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);
        $students[] = $student;

        if ($withGuardians) {
            $guardian = makeGuardian();
            app(AttachGuardianAction::class)->execute($student, $guardian, 'father');
            $guardians[] = $guardian;
        }
    }

    return [
        'year' => $year,
        'class' => $class,
        'teacher' => $teacher,
        'teacherUser' => User::query()->find($teacher->user_id),
        'students' => $students,
        'guardians' => $guardians,
    ];
}

/**
 * One student on a class roster, and the teacher who teaches that class.
 *
 * Shared for the same reason as seedClassWithFamilies(): E2a's tests, E2b's and
 * E22a's all need it, and a function declared in a test file only exists if
 * that file happens to have been loaded.
 */
function seedFamilyAndTeacher(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $teacher = makeTeacherRow();
    app(\App\Domains\Academics\Actions\SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return [
        'student' => $student,
        'studentUser' => User::query()->find($student->user_id),
        'teacher' => $teacher,
        'teacherUser' => User::query()->find($teacher->user_id),
        'class' => $class,
        'year' => $year,
    ];
}
