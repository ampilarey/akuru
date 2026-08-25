<?php

use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Actions\SubmitSchoolRequestAction;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\HR\Actions\AppendLeaveLedgerAction;
use App\Domains\HR\Actions\ApproveStaffLeaveAction;
use App\Domains\HR\Actions\CarryOverLeaveAction;
use App\Domains\HR\Actions\EnsureLeaveEntitlementAction;
use App\Domains\HR\Actions\LeaveBalanceCalculator;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\LeaveLedger;
use App\Domains\HR\Models\LeaveType;
use App\Domains\HR\Models\StaffAttendance;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function leaveType(string $code): LeaveType
{
    return LeaveType::query()->where('code', $code)->sole();
}

it('keeps leave balance equal to the ledger sum', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $staff = makeStaffProfile();
    $entitlement = app(EnsureLeaveEntitlementAction::class)->execute($staff->id, leaveType('annual')->id, $year->id);

    app(AppendLeaveLedgerAction::class)->execute($entitlement->id, -3.5, 'taken');
    app(AppendLeaveLedgerAction::class)->execute($entitlement->id, 2, 'adjustment: goodwill');
    app(AppendLeaveLedgerAction::class)->execute($entitlement->id, -1, 'taken');

    $sum = round((float) LeaveLedger::query()->where('entitlement_id', $entitlement->id)->sum('days'), 1);

    expect(app(LeaveBalanceCalculator::class)->execute($entitlement->id))->toBe($sum)
        ->and($sum)->toBe(17.5);
});

it('rejects over-balance paid leave and lets unpaid bypass the check', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();

    expect(fn () => app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'leave_type_id' => leaveType('annual')->id,
        'from_date' => '2026-08-01',
        'to_date' => '2026-08-31',
    ]))->toThrow(ValidationException::class);

    expect(LeaveLedger::query()->where('reason', 'taken')->count())->toBe(0)
        ->and(StaffAttendance::query()->count())->toBe(0);

    $result = app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'leave_type_id' => leaveType('unpaid')->id,
        'from_date' => '2026-08-01',
        'to_date' => '2026-08-10',
    ]);

    expect($result['days'])->toBe(10.0)
        ->and(StaffAttendance::query()->where('status', StaffAttendanceStatus::OnLeave->value)->count())->toBe(10);
});

it('caps carry-over at the leave type maximum', function () {
    $from = makeYear(['name' => '2025-2026', 'status' => 'closed', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
    $to = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();
    $annual = leaveType('annual');

    $old = app(EnsureLeaveEntitlementAction::class)->execute($staff->id, $annual->id, $from->id);
    app(AppendLeaveLedgerAction::class)->execute($old->id, -12, 'taken');

    $report = app(CarryOverLeaveAction::class)->execute($from->id, $to->id);
    $next = app(EnsureLeaveEntitlementAction::class)->execute($staff->id, $annual->id, $to->id);

    expect($report[0]['carried'])->toBe(5.0)
        ->and((float) $next->carried_over_days)->toBe(5.0)
        ->and(app(LeaveBalanceCalculator::class)->execute($next->id))->toBe(25.0)
        ->and(app(LeaveBalanceCalculator::class)->execute($old->id))->toBe(3.0);
});

it('approves staff leave into attendance, ledger, and teacher absences in one transaction', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();
    $teacher = makeTeacherRow();
    $staff = makeStaffProfile(['user_id' => $teacher->user_id]);
    DB::table('teachers')->where('id', $teacher->id)->update(['staff_profile_id' => $staff->id]);

    $entry = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $period->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    $requester = actingPeopleAdmin(['requests.submit']);
    $submitted = app(SubmitSchoolRequestAction::class)->execute([
        'type' => SchoolRequestType::StaffLeave->value,
        'requester_id' => $requester->id,
        'regarding_type' => 'staff_profile',
        'regarding_id' => $staff->id,
        'payload' => [
            'staff_profile_id' => $staff->id,
            'leave_type_id' => leaveType('annual')->id,
            'from_date' => '2026-08-24',
            'to_date' => '2026-08-24',
        ],
        'reason' => 'Medical leave',
    ]);

    $admin = actingPeopleAdmin(['requests.review']);
    app(ReviewSchoolRequestAction::class)->execute($submitted, SchoolRequestStatus::Approved, $admin->id);

    expect(TeacherAbsence::query()->where('teacher_id', $teacher->id)->where('status', 'approved')->count())->toBe(1)
        ->and(SubstitutionRequest::query()->where('timetable_entry_id', $entry->id)->where('status', 'open')->count())->toBe(1)
        ->and(StaffAttendance::query()->where('staff_profile_id', $staff->id)->sole()->status)->toBe(StaffAttendanceStatus::OnLeave)
        ->and((float) LeaveLedger::query()->where('reason', 'taken')->sum('days'))->toBe(-1.0);

    $tooMuch = app(SubmitSchoolRequestAction::class)->execute([
        'type' => SchoolRequestType::StaffLeave->value,
        'requester_id' => $requester->id,
        'regarding_type' => 'staff_profile',
        'regarding_id' => $staff->id,
        'payload' => [
            'staff_profile_id' => $staff->id,
            'leave_type_id' => leaveType('annual')->id,
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
        ],
        'reason' => 'Too many days',
    ]);

    expect(fn () => app(ReviewSchoolRequestAction::class)->execute($tooMuch, SchoolRequestStatus::Approved, $admin->id))
        ->toThrow(ValidationException::class);

    expect($tooMuch->fresh()->status)->toBe(SchoolRequestStatus::Pending)
        ->and(TeacherAbsence::query()->count())->toBe(1)
        ->and(StaffAttendance::query()->count())->toBe(1)
        ->and(LeaveLedger::query()->where('reason', 'taken')->count())->toBe(1);
});

it('lists leave types and balances for hr.manage and own balances in the portal', function () {
    $admin = actingPeopleAdmin(['hr.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $mine = makeStaffProfile();
    $other = makeStaffProfile();
    app(EnsureLeaveEntitlementAction::class)->execute($mine->id, leaveType('annual')->id, $year->id);
    app(EnsureLeaveEntitlementAction::class)->execute($other->id, leaveType('sick')->id, $year->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.leave-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Leave/Types')
            ->has('types', 8)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.leave-types.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('annual');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.leave-balances.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Leave/Balances')
            ->has('rows', 2)
        );

    $staffUser = User::query()->findOrFail($mine->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($staffUser)
        ->get(route('portal.leave'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/LeaveBalances')
            ->has('rows', 1)
            ->where('rows.0.staff_profile_id', $mine->id)
        );
});

it('keeps existing teacher_leave approval working without a leave type', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $submitted = app(SubmitSchoolRequestAction::class)->execute([
        'type' => SchoolRequestType::TeacherLeave->value,
        'requester_id' => actingPeopleAdmin(['requests.submit'])->id,
        'regarding_type' => 'teacher',
        'regarding_id' => $teacher->id,
        'payload' => [
            'teacher_id' => $teacher->id,
            'from_date' => '2026-08-24',
            'to_date' => '2026-08-24',
        ],
        'reason' => 'Legacy leave',
    ]);

    app(ReviewSchoolRequestAction::class)->execute(
        $submitted,
        SchoolRequestStatus::Approved,
        actingPeopleAdmin(['requests.review'])->id,
    );

    expect(TeacherAbsence::query()->count())->toBe(1)
        ->and(SchoolRequest::query()->sole()->status)->toBe(SchoolRequestStatus::Approved)
        ->and(LeaveLedger::query()->count())->toBe(0);
});
