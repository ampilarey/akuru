<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Actions\SaveRoomBookingAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamStatusAudit;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function examAdmin(): \App\Domains\Identity\Models\User
{
    return actingPeopleAdmin(['exams.manage']);
}

function examContext(): array
{
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $subject = makeSubject();
    $type = ExamType::query()->where('code', ExamTypeCode::Final)->sole();

    return compact('year', 'term', 'class', 'subject', 'type');
}

function examPayload(array $ctx, array $overrides = []): array
{
    return array_merge([
        'academic_year_id' => $ctx['year']->id,
        'term_id' => $ctx['term']->id,
        'class_id' => $ctx['class']->id,
        'subject_id' => $ctx['subject']->id,
        'exam_type_id' => $ctx['type']->id,
        'name' => 'Term 1 Final',
        'exam_date' => '2026-08-24',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'max_marks' => 100,
    ], $overrides);
}

it('creates lists and exports exams', function () {
    $admin = examAdmin();
    $ctx = examContext();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.store'), examPayload($ctx))
        ->assertRedirect();

    expect(Exam::query()->sole()->name)->toBe('Term 1 Final')
        ->and(Exam::query()->sole()->status)->toBe(ExamStatus::Scheduled);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.index', ['academic_year_id' => $ctx['year']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExamsGrades/Exams/Index')
            ->has('exams', 1)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Term 1 Final');
});

it('warn-blocks a holiday unless confirm_calendar is set', function () {
    $ctx = examContext();
    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'date' => '2026-08-28',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'Independence Day',
    ]);

    expect(fn () => app(SaveExamAction::class)->execute(examPayload($ctx, ['exam_date' => '2026-08-28'])))
        ->toThrow(ValidationException::class);

    $exam = app(SaveExamAction::class)->execute(examPayload($ctx, [
        'exam_date' => '2026-08-28',
        'confirm_calendar' => true,
    ]));

    expect($exam->exam_date?->toDateString())->toBe('2026-08-28');
});

it('warns when the class already has the max exams that day', function () {
    $ctx = examContext();
    app(SaveExamAction::class)->execute(examPayload($ctx, ['name' => 'First']));

    expect(fn () => app(SaveExamAction::class)->execute(examPayload($ctx, [
        'name' => 'Second',
        'subject_id' => makeSubject()->id,
    ])))->toThrow(ValidationException::class);

    $second = app(SaveExamAction::class)->execute(examPayload($ctx, [
        'name' => 'Second',
        'subject_id' => makeSubject()->id,
        'confirm_same_day' => true,
    ]));

    expect($second->name)->toBe('Second');
});

it('warns on a room booking clash unless confirm_room is set', function () {
    $admin = examAdmin();
    $ctx = examContext();
    $room = makeRoomRow('Hall A');

    app(SaveRoomBookingAction::class)->execute([
        'academic_year_id' => $ctx['year']->id,
        'room_id' => $room->id,
        'title' => 'Parents evening',
        'date' => '2026-08-24',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ], null, $admin->id);

    expect(fn () => app(SaveExamAction::class)->execute(examPayload($ctx, [
        'room_id' => $room->id,
    ])))->toThrow(ValidationException::class);

    $exam = app(SaveExamAction::class)->execute(examPayload($ctx, [
        'room_id' => $room->id,
        'confirm_room' => true,
    ]));

    expect($exam->room_id)->toBe($room->id);
});

it('bulk schedules one exam per subject', function () {
    $admin = examAdmin();
    $ctx = examContext();
    $other = makeSubject();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.bulk'), [
            ...examPayload($ctx, ['name' => 'Term 1 Finals — Grade 5', 'confirm_same_day' => true]),
            'subject_ids' => [$ctx['subject']->id, $other->id],
        ])
        ->assertRedirect();

    expect(Exam::query()->count())->toBe(2)
        ->and(Exam::query()->pluck('subject_id')->sort()->values()->all())
        ->toBe(collect([$ctx['subject']->id, $other->id])->sort()->values()->all());
});

it('walks the status flow, publishes to portal, and audits unlock', function () {
    $admin = examAdmin();
    $ctx = examContext();
    $student = makeStudent();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $student->id);

    $exam = app(SaveExamAction::class)->execute(examPayload($ctx), null, $admin->id);

    foreach ([ExamStatus::MarksEntry, ExamStatus::Review, ExamStatus::Published] as $status) {
        $this->withoutLocalizationMiddleware()
            ->actingAs($admin)
            ->post(route('exams.transition', $exam), ['status' => $status->value])
            ->assertRedirect();
        $exam->refresh();
    }

    expect($exam->status)->toBe(ExamStatus::Published)
        ->and($exam->published_at)->not->toBeNull()
        ->and(DB::table('app_notifications')->where('type', 'exam_results')->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs(\App\Domains\Identity\Models\User::query()->findOrFail($guardian->user_id))
        ->get(route('portal.exams'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Exams')
            ->has('exams', 1)
            ->where('exams.0.name', 'Term 1 Final')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.transition', $exam), ['status' => ExamStatus::Locked->value])
        ->assertRedirect();

    expect(fn () => app(SaveExamAction::class)->execute(examPayload($ctx, ['name' => 'Edited']), $exam->fresh()))
        ->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('exams.index'))
        ->post(route('exams.transition', $exam), ['status' => ExamStatus::Review->value])
        ->assertRedirect();

    expect($exam->fresh()->status)->toBe(ExamStatus::Locked);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('exams.transition', $exam), [
            'status' => ExamStatus::Review->value,
            'reason' => 'Mark correction after appeal',
        ])
        ->assertRedirect();

    expect($exam->fresh()->status)->toBe(ExamStatus::Review)
        ->and(ExamStatusAudit::query()->where('exam_id', $exam->id)->where('from_status', 'locked')->sole()->reason)
        ->toBe('Mark correction after appeal');
});

it('hides unpublished exams from the portal', function () {
    $ctx = examContext();
    $student = makeStudent();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($ctx['class'], $student->id);
    app(SaveExamAction::class)->execute(examPayload($ctx));

    $this->withoutLocalizationMiddleware()
        ->actingAs(\App\Domains\Identity\Models\User::query()->findOrFail($guardian->user_id))
        ->get(route('portal.exams'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Exams')->has('exams', 0));
});
