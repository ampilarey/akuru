<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveExamMarkAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamMark;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function marksAdmin(): User
{
    return actingPeopleAdmin(['exams.manage', 'exams.enter-any']);
}

function marksContext(): array
{
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $subject = makeSubject();
    $type = ExamType::query()->where('code', ExamTypeCode::Final)->sole();
    $exam = app(SaveExamAction::class)->execute([
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'exam_type_id' => $type->id,
        'name' => 'Term 1 Final',
        'exam_date' => '2026-08-24',
        'max_marks' => 50,
    ]);

    return compact('year', 'term', 'class', 'subject', 'type', 'exam');
}

function openMarks(\App\Domains\ExamsGrades\Models\Exam $exam, User $admin): void
{
    app(TransitionExamStatusAction::class)->execute($exam, ExamStatus::MarksEntry, $admin->id);
}

it('enters marks on the historical roster and rejects marks above max', function () {
    $admin = marksAdmin();
    $ctx = marksContext();
    $present = makeStudent(['first_name' => 'Aisha']);
    $left = makeStudent(['first_name' => 'Yusuf']);
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $present->id, '2026-01-01');
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $left->id, '2026-01-01');
    ClassStudent::query()->where('student_id', $left->id)->update([
        'left_at' => '2026-08-01',
        'status' => 'left',
    ]);
    openMarks($ctx['exam'], $admin);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.marks.show', $ctx['exam']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Marks/Show')
            ->has('rows', 1)
            ->where('rows.0.student_id', $present->id)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('exams.marks.update', $ctx['exam']), [
            'student_id' => $present->id,
            'marks' => 41,
        ])
        ->assertRedirect();

    expect(ExamMark::query()->sole()->marks)->toEqual(41);

    expect(fn () => app(SaveExamMarkAction::class)->execute($ctx['exam']->fresh(), $present->id, [
        'marks' => 51,
    ], $admin->id))->toThrow(ValidationException::class);

    expect(fn () => app(SaveExamMarkAction::class)->execute($ctx['exam']->fresh(), $left->id, [
        'marks' => 10,
    ], $admin->id))->toThrow(ValidationException::class);
});

it('rejects absent or exempt combined with marks', function () {
    $admin = marksAdmin();
    $ctx = marksContext();
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $student->id, '2026-01-01');
    openMarks($ctx['exam'], $admin);

    expect(fn () => app(SaveExamMarkAction::class)->execute($ctx['exam'], $student->id, [
        'marks' => 20,
        'is_absent' => true,
    ], $admin->id))->toThrow(ValidationException::class);

    $absent = app(SaveExamMarkAction::class)->execute($ctx['exam'], $student->id, [
        'is_absent' => true,
    ], $admin->id);

    expect($absent->is_absent)->toBeTrue()->and($absent->marks)->toBeNull();
});

it('lets the subject teacher enter marks and forbids another teacher', function () {
    $admin = marksAdmin();
    $ctx = marksContext();
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $student->id, '2026-01-01');
    openMarks($ctx['exam'], $admin);

    $assigned = makeTeacherRow();
    $other = makeTeacherRow();
    Role::findOrCreate('teacher', 'web');
    $assignedUser = User::query()->findOrFail($assigned->user_id);
    $otherUser = User::query()->findOrFail($other->user_id);
    $assignedUser->assignRole('teacher');
    $otherUser->assignRole('teacher');

    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'teacher_id' => $assigned->id,
        'academic_year_id' => $ctx['year']->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($assignedUser)
        ->put(route('exams.marks.update', $ctx['exam']), [
            'student_id' => $student->id,
            'marks' => 33,
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->get(route('exams.marks.show', $ctx['exam']))
        ->assertForbidden();
});

it('imports and exports marks csv and publishes them to the portal', function () {
    $admin = marksAdmin();
    $ctx = marksContext();
    $student = makeStudent(['first_name' => 'Aisha']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $student->id, '2026-01-01');
    openMarks($ctx['exam'], $admin);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.marks.import', $ctx['exam']), [
            'rows' => [[
                'student_id' => $student->id,
                'marks' => 44,
                'is_absent' => false,
                'is_exempt' => false,
            ]],
        ])
        ->assertRedirect();

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.marks.export', $ctx['exam']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Aisha')->toContain('44');

    app(TransitionExamStatusAction::class)->execute($ctx['exam']->fresh(), ExamStatus::Review, $admin->id);
    app(TransitionExamStatusAction::class)->execute($ctx['exam']->fresh(), ExamStatus::Published, $admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($guardian->user_id))
        ->get(route('portal.exams'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Exams')
            ->where('exams.0.marks', '44.00')
        );
});

it('rejects mark edits after the exam is locked', function () {
    $admin = marksAdmin();
    $ctx = marksContext();
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $student->id, '2026-01-01');
    openMarks($ctx['exam'], $admin);
    app(SaveExamMarkAction::class)->execute($ctx['exam'], $student->id, ['marks' => 20], $admin->id);

    foreach ([ExamStatus::Review, ExamStatus::Published, ExamStatus::Locked] as $status) {
        app(TransitionExamStatusAction::class)->execute($ctx['exam']->fresh(), $status, $admin->id);
    }

    expect(fn () => app(SaveExamMarkAction::class)->execute($ctx['exam']->fresh(), $student->id, [
        'marks' => 30,
    ], $admin->id))->toThrow(ValidationException::class);
});
