<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListBehaviorRecordsAction;
use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\RecordClassAttendanceAction;
use App\Domains\Academics\Actions\SaveBehaviorRecordAction;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\ExamsGrades\Actions\AssembleReportCardDataAction;
use App\Domains\ExamsGrades\Actions\GenerateReportCardsAction;
use App\Domains\ExamsGrades\Actions\GenerateTranscriptAction;
use App\Domains\ExamsGrades\Actions\PublishReportCardsAction;
use App\Domains\ExamsGrades\Actions\SaveReportCardCommentAction;
use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\TermGrade;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Services\HtmlDocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function reportAdmin(): User
{
    return actingPeopleAdmin(['exams.manage']);
}

function reportSetup(): array
{
    app()->instance(SmsSenderInterface::class, new class implements SmsSenderInterface
    {
        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            return ['success' => true];
        }
    });

    $admin = reportAdmin();
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $subject = makeSubject();
    $student = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Ali', 'student_id' => 'STU-1001']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');

    TermGrade::query()->create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'academic_year_id' => $year->id,
        'weighted_percent' => 88.50,
        'grade' => 'A',
        'grade_point' => 4.00,
        'rank' => 1,
        'components' => ['quiz' => 20],
        'computed_at' => now(),
    ]);

    $writer = app(RecordClassAttendanceAction::class);
    $writer->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: '2026-02-01',
        status: AttendanceStatus::Present,
        source: AttendanceSource::Daily,
        markedBy: $admin->id,
        termId: $term->id,
    ));
    $writer->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: '2026-02-02',
        status: AttendanceStatus::Absent,
        source: AttendanceSource::Daily,
        markedBy: $admin->id,
        termId: $term->id,
    ));

    app(SaveBehaviorRecordAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'type' => BehaviorType::Compliment->value,
        'category' => 'conduct',
        'description' => 'Helped a classmate',
        'date' => '2026-02-03',
        'recorded_by' => $admin->id,
        'parent_visible' => true,
    ]);
    app(SaveBehaviorRecordAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'type' => BehaviorType::Notice->value,
        'category' => 'homework',
        'description' => 'Internal note',
        'date' => '2026-02-04',
        'recorded_by' => $admin->id,
        'parent_visible' => false,
    ]);

    return compact('admin', 'year', 'term', 'class', 'subject', 'student', 'guardian');
}

it('binds the production html document renderer', function () {
    expect(app(DocumentRendererInterface::class))->toBeInstanceOf(HtmlDocumentRenderer::class);
});

it('generates report cards with attendance and parent-visible behavior matching S2', function () {
    $ctx = reportSetup();

    $cards = app(GenerateReportCardsAction::class)->execute(
        $ctx['class']->id,
        $ctx['term']->id,
        null,
        'en',
        $ctx['admin']->id,
        false,
    );

    expect($cards)->toHaveCount(1);
    $card = $cards->first();
    expect($card->status)->toBe(ReportCardStatus::Ready)
        ->and($card->document_id)->not->toBeNull();

    $payload = app(AssembleReportCardDataAction::class)->execute($card->fresh(['template', 'comments']));
    $attendance = app(ListClassAttendanceAction::class)->studentSummary($ctx['student']->id, $ctx['year']->id)->first();
    $behavior = app(ListBehaviorRecordsAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'academic_year_id' => $ctx['year']->id,
        'parent_visible' => true,
    ]);

    expect($payload['attendance']['percent'])->toBe($attendance['percent'])
        ->and($payload['attendance']['absent'])->toBe(1)
        ->and($payload['attendance']['present'])->toBe(1)
        ->and($payload['behavior']['total'])->toBe($behavior->count())
        ->and($payload['behavior']['total'])->toBe(1)
        ->and($payload['grades'][0]['grade'])->toBe('A');

    $en = app(DocumentRendererInterface::class)->render('report-card', $payload);
    expect($en)->toContain('dir="ltr"')
        ->and($en)->toContain('Aisha Ali')
        ->and($en)->toContain('Percent: '.$attendance['percent'])
        ->and($en)->toContain('Helped a classmate')
        ->and($en)->not->toContain('Internal note');

    $dvPayload = app(AssembleReportCardDataAction::class)->execute($card->fresh(['template', 'comments']), 'dv');
    $dv = app(DocumentRendererInterface::class)->render('report-card', $dvPayload);
    expect($dv)->toContain('dir="rtl"')
        ->and($dv)->toContain('lang="dv"')
        ->and($dv)->toContain('Attendance (DV)');
});

it('publishes report cards to the portal and refuses regeneration after publish', function () {
    $ctx = reportSetup();
    app(GenerateReportCardsAction::class)->execute(
        $ctx['class']->id,
        $ctx['term']->id,
        null,
        'en',
        $ctx['admin']->id,
        false,
    );
    $card = ReportCard::query()->sole();
    app(SaveReportCardCommentAction::class)->execute([
        'report_card_id' => $card->id,
        'comment_type' => 'class_teacher',
        'comment' => 'Excellent term.',
    ], $ctx['admin']->id);

    app(GenerateReportCardsAction::class)->renderOne($card->id, 'en', $ctx['admin']->id);
    app(PublishReportCardsAction::class)->execute($ctx['class']->id, $ctx['term']->id);

    expect($card->fresh()->status)->toBe(ReportCardStatus::Published)
        ->and(DB::table('app_notifications')->where('type', 'report_cards')->count())->toBe(1);

    expect(fn () => app(GenerateReportCardsAction::class)->renderOne($card->id))
        ->toThrow(ValidationException::class);

    $guardianUser = User::query()->findOrFail($ctx['guardian']->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.report-cards'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/ReportCards')
            ->has('cards', 1)
        );

    $download = $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.report-cards.download', $card))
        ->assertOk();
    expect($download->getContent())->toContain('Aisha Ali')->toContain('Excellent term.');

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.report-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ExamsGrades/ReportCards/Index'));

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.report-cards.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Aisha Ali')->toContain('published');
});

it('builds a cumulative transcript with optional gpa', function () {
    $ctx = reportSetup();
    $otherSubject = makeSubject();
    TermGrade::query()->create([
        'student_id' => $ctx['student']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $otherSubject->id,
        'term_id' => $ctx['term']->id,
        'academic_year_id' => $ctx['year']->id,
        'weighted_percent' => 70,
        'grade' => 'B',
        'grade_point' => 3.00,
        'rank' => 2,
        'components' => [],
        'computed_at' => now(),
    ]);

    $result = app(GenerateTranscriptAction::class)->execute($ctx['student']->id, 'en', $ctx['admin']->id);
    expect($result['gpa'])->toBe(3.5)
        ->and($result['html'])->toContain('Academic transcript')
        ->and($result['html'])->toContain('GPA')
        ->and($result['rows'])->toHaveCount(2);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('exams.transcript', ['student_id' => $ctx['student']->id]))
        ->assertOk()
        ->assertSee('Academic transcript');
});
