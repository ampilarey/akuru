<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\MigrateLegacyAssessmentsAction;
use App\Domains\Academics\Legacy\Models\Assignment;
use App\Domains\Academics\Legacy\Models\AssignmentSubmission;
use App\Domains\Academics\Legacy\Models\Quiz;
use App\Domains\Academics\Legacy\Models\QuizAttempt;
use App\Domains\Academics\Legacy\Models\QuizQuestion;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('passes verify when there are no legacy quiz or assignment rows', function () {
    $this->artisan('assessments:verify-legacy-migration')
        ->expectsOutputToContain('assessments:verify-legacy-migration OK')
        ->assertSuccessful();
});

it('attaches an assessment to a class or a course but not both', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'A');

    $classAssessment = app(SaveAssessmentAction::class)->execute([
        'classroom_id' => $class->id,
        'academic_year_id' => $year->id,
        'title' => 'Class quiz',
        'status' => 'published',
        'created_by' => $admin->id,
    ]);

    expect($classAssessment->course_id)->toBeNull()
        ->and($classAssessment->classroom_id)->toBe($class->id);

    expect(fn () => app(SaveAssessmentAction::class)->execute([
        'title' => 'Unattached',
    ]))->toThrow(ValidationException::class);

    $course = app(\App\Domains\Courses\Actions\SaveEngineCourseAction::class)->execute([
        'title' => 'Course home',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);

    expect(fn () => app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'classroom_id' => $class->id,
        'title' => 'Both',
    ]))->toThrow(ValidationException::class);
});

it('migrates class quizzes and assignments onto the engine and verifies remaining counts', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'A');
    $subject = makeSubject();
    $teacher = makeTeacherRow();
    $student = makeStudent(['first_name' => 'Mariyam', 'last_name' => 'Ali']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    $quiz = Quiz::query()->create([
        'title' => 'Surah quiz',
        'description' => 'Letters',
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'teacher_id' => $teacher->id,
        'time_limit_min' => 20,
        'max_attempts' => 2,
        'passing_score' => 50,
        'show_results' => true,
        'shuffle_questions' => false,
        'status' => 'published',
    ]);
    $question = QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'order' => 1,
        'type' => 'mcq',
        'body' => 'What is alif?',
        'options' => ['A letter', 'A book'],
        'answer' => [0],
        'points' => 5,
    ]);
    QuizAttempt::query()->create([
        'quiz_id' => $quiz->id,
        'student_id' => $student->id,
        'attempt_number' => 1,
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'score' => 100,
        'points_earned' => 5,
        'total_points' => 5,
        'answers' => [$question->id => 0],
        'status' => 'completed',
        'feedback' => 'MashaAllah',
    ]);

    $orphan = Quiz::query()->create([
        'title' => 'No class',
        'subject_id' => $subject->id,
        'classroom_id' => null,
        'teacher_id' => $teacher->id,
        'status' => 'draft',
    ]);

    $assignment = Assignment::query()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'title' => 'Recitation homework',
        'description' => 'Record ayah 1',
        'instructions' => 'Upload audio',
        'due_date' => '2026-09-15',
        'due_time' => '23:59',
        'max_marks' => 10,
        'type' => 'homework',
        'status' => 'published',
        'is_active' => true,
    ]);
    AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'content' => 'https://example.test/ayah.mp3',
        'submitted_at' => now(),
        'status' => 'graded',
        'marks_obtained' => 9,
        'teacher_feedback' => 'Clear recitation',
        'graded_by' => $teacher->user_id,
        'graded_at' => now(),
        'is_late' => false,
    ]);

    $this->artisan('assessments:verify-legacy-migration')
        ->expectsOutputToContain('remaining')
        ->assertFailed();

    $this->artisan('assessments:verify-legacy-migration', ['--backfill' => true])
        ->expectsOutputToContain('quizzes: 1 migrated / 2 source, 1 remaining')
        ->expectsOutputToContain((string) $orphan->id)
        ->assertFailed();

    $orphan->update(['classroom_id' => $class->id]);

    $this->artisan('assessments:verify-legacy-migration', ['--backfill' => true])
        ->expectsOutputToContain('assessments:verify-legacy-migration OK')
        ->assertSuccessful();

    $this->artisan('assessments:verify-legacy-migration', ['--backfill' => true])
        ->assertSuccessful();

    $engineQuiz = Assessment::query()->where('legacy_quiz_id', $quiz->id)->sole();
    expect($engineQuiz->classroom_id)->toBe($class->id)
        ->and($engineQuiz->course_id)->toBeNull()
        ->and($engineQuiz->academic_year_id)->toBe($year->id)
        ->and($engineQuiz->questions)->toHaveCount(1);

    $engineAttempt = AssessmentAttempt::query()->where('legacy_quiz_attempt_id', 1)->first()
        ?? AssessmentAttempt::query()->where('assessment_id', $engineQuiz->id)->where('student_id', $student->id)->first();
    expect($engineAttempt)->not->toBeNull()
        ->and($engineAttempt->classroom_id)->toBe($class->id)
        ->and($engineAttempt->enrollment_id)->toBeNull()
        ->and($engineAttempt->score)->toBe(5)
        ->and($engineAttempt->feedback)->toBe('MashaAllah');

    $engineAssignment = Assessment::query()->where('legacy_assignment_id', $assignment->id)->sole();
    expect($engineAssignment->assessment_type)->toBe('assignment')
        ->and($engineAssignment->requires_teacher_marking)->toBeTrue();

    expect(config('morph-map'))->toHaveKeys(['quiz', 'quiz_question', 'quiz_attempt', 'assignment', 'assignment_submission', 'assessment', 'class_room']);

    $report = app(MigrateLegacyAssessmentsAction::class)->verify();
    expect($report['quizzes']['remaining'])->toBe([])
        ->and($report['assignments']['remaining'])->toBe([]);
});

it('lists migrated class assessments with csv and lets roster students open the player', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'A');
    $subject = makeSubject();
    $teacher = makeTeacherRow();
    $student = makeStudent(['first_name' => 'Khadheeja', 'last_name' => 'Didi']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    Quiz::query()->create([
        'title' => 'Grade 5 letters',
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'teacher_id' => $teacher->id,
        'status' => 'published',
        'show_results' => true,
    ]);
    $quiz = Quiz::query()->first();
    QuizQuestion::query()->create([
        'quiz_id' => $quiz->id,
        'order' => 1,
        'type' => 'mcq',
        'body' => 'Ba is a letter?',
        'options' => ['Yes', 'No'],
        'answer' => [0],
        'points' => 1,
    ]);

    app(MigrateLegacyAssessmentsAction::class)->execute();
    $assessment = Assessment::query()->where('legacy_quiz_id', $quiz->id)->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', $class))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->has('assessments', 1)
            ->where('assessments.0.title', 'Grade 5 letters')
            ->where('assessments.0.legacy_quiz_id', $quiz->id)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.assessments.export', $class))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $studentUser = User::query()->findOrFail($student->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->get(route('learn.assessments.show', $assessment->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Assessment')
            ->where('assessment.title', 'Grade 5 letters')
            ->where('enrollment.classroom_id', $class->id)
            ->where('enrollment.course_id', null)
        );

    $outsider = makeStudent(['first_name' => 'Out', 'last_name' => 'Sider']);
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($outsider->user_id))
        ->get(route('learn.assessments.show', $assessment->id))
        ->assertForbidden();
});
