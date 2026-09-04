<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\SaveExamMarkAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\Portal\Actions\ComposePortalHomeAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('composes parent home from attendance, exams, invoices, courses, and hifz contracts', function () {
    Role::findOrCreate('parent', 'web');
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish', 'exams.manage', 'exams.enter-any', 'finance.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $subject = makeSubject();

    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Layla', 'last_name' => 'Hassan']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Portal Home Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'One',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

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
    app(TransitionExamStatusAction::class)->execute($exam, ExamStatus::MarksEntry, $admin->id);
    app(SaveExamMarkAction::class)->execute($exam->fresh(), $student->id, ['marks' => 41], $admin->id);
    app(TransitionExamStatusAction::class)->execute($exam->fresh(), ExamStatus::Review, $admin->id);
    app(TransitionExamStatusAction::class)->execute($exam->fresh(), ExamStatus::Published, $admin->id);

    Invoice::query()->create([
        'invoice_number' => 'INV-HOME-1',
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'invoice_type' => InvoiceType::SchoolFees,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'status' => InvoiceStatus::Sent,
        'total_amount' => 1000,
        'paid_amount' => 250,
        'created_by' => $admin->id,
    ]);

    $program = HifzProgram::query()->create([
        'name' => 'Juz Amma track',
        'status' => 'active',
        'academic_year_id' => $year->id,
    ]);
    HifzEnrollment::query()->create([
        'hifz_program_id' => $program->id,
        'student_id' => $student->id,
        'start_date' => '2026-01-01',
        'status' => 'active',
        'current_juz' => 30,
    ]);

    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, true);
    $guardianUser = User::query()->findOrFail($guardian->user_id);
    $guardianUser->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.home'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Home')
            ->where('title', 'Parent Dashboard')
            ->has('students', 1)
            ->where('students.0.name', 'Layla Hassan')
            ->where('students.0.courses.0.course_title', 'Portal Home Lab')
            ->where('students.0.exams.0.name', 'Term 1 Final')
            ->where('students.0.exams.0.marks', 41)
            ->where('students.0.invoices.0.invoice_number', 'INV-HOME-1')
            ->where('students.0.hifz.0.program', 'Juz Amma track')
            ->where('students.0.hifz.0.current_juz', 30)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.home.export'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Layla Hassan')
        ->and($csv->streamedContent())->toContain('INV-HOME-1')
        ->and($csv->streamedContent())->toContain('Juz Amma track')
        ->and($csv->streamedContent())->toContain('Portal Home Lab');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Student Dashboard')
            ->where('students.0.relationship', 'self')
            ->where('students.0.name', 'Layla Hassan')
        );
});

it('does not import other domain models or the Hifz namespace from new portal home files', function () {
    $files = [
        app_path('Domains/Portal/Actions/ComposePortalHomeAction.php'),
        app_path('Domains/Portal/Http/Controllers/PortalHomeController.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toMatch('/App\\\\Domains\\\\[A-Za-z]+\\\\Models\\\\/');
    }
});

it('composes tiles whose counts match the payload they summarise', function () {
    $user = User::factory()->create();
    Role::findOrCreate('student', 'web');
    $user->assignRole('student');
    makeStudent(['user_id' => $user->id]);

    $payload = app(ComposePortalHomeAction::class)->execute((int) $user->id);

    expect($payload)->toHaveKey('tiles')
        ->and($payload)->toHaveKey('nextSchoolDay');

    $tiles = collect($payload['tiles'])->keyBy('key');

    // Every tile must summarise, not just link: a status line is the point.
    $tiles->each(function (array $tile): void {
        expect($tile['status'])->not->toBeEmpty()
            ->and($tile)->toHaveKeys(['key', 'label', 'href', 'badge', 'status']);
    });

    // Counts are derived from the same arrays the page renders, so they
    // cannot drift from the module they point at.
    $composed = collect($payload['students']);
    expect($tiles->get('exams')['badge'])
        ->toBe($composed->sum(fn (array $row): int => count($row['exams'])) ?: null);

    // Hifz is omitted rather than shown empty on a glanceable screen.
    if ($composed->sum(fn (array $row): int => count($row['hifz'])) === 0) {
        expect($tiles->has('hifz'))->toBeFalse();
    }
});
