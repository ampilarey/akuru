<?php

use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Actions\SubmitSchoolRequestAction;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('approves teacher leave into absences and the timetable overlay', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();
    $teacher = makeTeacherRow();
    $subject = makeSubject();

    $entry = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $period->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    $requester = actingPeopleAdmin(['requests.submit']);
    $submitted = app(SubmitSchoolRequestAction::class)->execute([
        'type' => SchoolRequestType::TeacherLeave->value,
        'requester_id' => $requester->id,
        'regarding_type' => 'teacher',
        'regarding_id' => $teacher->id,
        'payload' => [
            'teacher_id' => $teacher->id,
            'from_date' => '2026-08-24',
            'to_date' => '2026-08-24',
        ],
        'reason' => 'Medical leave',
    ]);

    $admin = actingPeopleAdmin(['requests.review', 'manage_timetables']);
    app(ReviewSchoolRequestAction::class)->execute(
        $submitted,
        SchoolRequestStatus::Approved,
        $admin->id,
        'Approved',
    );

    expect(TeacherAbsence::query()->where('teacher_id', $teacher->id)->where('status', 'approved')->count())->toBe(1)
        ->and(SubstitutionRequest::query()->where('timetable_entry_id', $entry->id)->where('status', 'open')->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.timetable.index', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Timetable/Builder')
            ->has('substitutions', 1)
            ->where('substitutions.0.timetable_id', $entry->id)
            ->where('substitutions.0.absent_teacher_id', $teacher->id)
        );
});

it('lets a teacher submit leave and an admin review it', function () {
    $teacher = makeTeacherRow();
    $user = \App\Domains\Identity\Models\User::query()->findOrFail($teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('requests.submit', 'web');
    $user->givePermissionTo('requests.submit');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('academics.requests.store'), [
            'type' => SchoolRequestType::TeacherLeave->value,
            'reason' => 'Family event',
            'from_date' => '2026-08-31',
            'to_date' => '2026-08-31',
        ])
        ->assertRedirect();

    $admin = actingPeopleAdmin(['requests.review', 'requests.submit']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Requests/Index')
            ->has('requests', 1)
        );
});
