<?php

use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\HR\Actions\AutoFillHolidayStaffAttendanceAction;
use App\Domains\HR\Actions\ImportStaffAttendanceCsvAction;
use App\Domains\HR\Actions\SummarizeStaffAttendanceMonthAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\StaffAttendance;
use App\Domains\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('adds department fields and a unique staff-date attendance row', function () {
    expect(Schema::hasColumns('staff_profiles', ['department', 'designation']))->toBeTrue()
        ->and(Schema::hasTable('staff_attendance'))->toBeTrue()
        ->and(Schema::hasColumns('staff_attendance', [
            'staff_profile_id',
            'academic_year_id',
            'date',
            'check_in',
            'check_out',
            'status',
            'source',
            'minutes_late',
            'marked_by',
            'remarks',
        ]))->toBeTrue()
        ->and(Permission::query()->where('name', 'hr.manage')->exists())->toBeTrue();

    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $staff = makeStaffProfile(['department' => 'Arabic', 'designation' => 'Teacher']);

    $writer = app(StaffAttendanceWriterInterface::class);
    $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-25',
        status: StaffAttendanceStatus::Present,
        source: StaffAttendanceSource::Manual,
    ));

    expect(StaffAttendance::query()->count())->toBe(1);

    expect(fn () => StaffAttendance::query()->create([
        'staff_profile_id' => $staff->id,
        'academic_year_id' => $year->id,
        'date' => '2026-08-25',
        'status' => StaffAttendanceStatus::Absent->value,
        'source' => StaffAttendanceSource::Manual->value,
    ]))->toThrow(QueryException::class);
});

it('keeps on_leave ahead of holiday and holiday ahead of present', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();
    $writer = app(StaffAttendanceWriterInterface::class);

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-08-28',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'Independence Day',
    ]);

    $holidayRow = $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-28',
        status: StaffAttendanceStatus::Present,
        source: StaffAttendanceSource::Manual,
    ));

    expect($holidayRow->status)->toBe(StaffAttendanceStatus::Holiday);

    $leaveRow = $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-28',
        status: StaffAttendanceStatus::OnLeave,
        source: StaffAttendanceSource::Manual,
    ));

    expect($leaveRow->status)->toBe(StaffAttendanceStatus::OnLeave)
        ->and(StaffAttendance::query()->count())->toBe(1);

    $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-28',
        status: StaffAttendanceStatus::Present,
        source: StaffAttendanceSource::Manual,
    ));

    expect(StaffAttendance::query()->sole()->status)->toBe(StaffAttendanceStatus::OnLeave);

    $other = makeStaffProfile();
    app(AutoFillHolidayStaffAttendanceAction::class)->execute($year->id);

    expect(StaffAttendance::query()->where('staff_profile_id', $staff->id)->sole()->status)->toBe(StaffAttendanceStatus::OnLeave)
        ->and(StaffAttendance::query()->where('staff_profile_id', $other->id)->sole()->status)->toBe(StaffAttendanceStatus::Holiday);
});

it('marks exports and imports staff attendance from the admin screen', function () {
    $admin = actingPeopleAdmin(['hr.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile(['department' => 'Quran']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.attendance.index', ['date' => '2026-08-25']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Attendance/Index')
            ->has('staff', 1)
            ->where('date', '2026-08-25')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('hr.attendance.store'), [
            'staff_profile_id' => $staff->id,
            'date' => '2026-08-25',
            'status' => StaffAttendanceStatus::Late->value,
            'minutes_late' => 12,
            'remarks' => 'Traffic',
        ])
        ->assertRedirect();

    expect(StaffAttendance::query()->sole()->status)->toBe(StaffAttendanceStatus::Late)
        ->and(StaffAttendance::query()->sole()->academic_year_id)->toBe($year->id);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.attendance.export', ['date' => '2026-08-25']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Traffic')->toContain('late');

    $other = makeStaffProfile(['staff_number' => 'STF-IMP']);
    $upload = UploadedFile::fake()->createWithContent('attendance.csv', implode("\n", [
        'staff_number,date,status,minutes_late,remarks',
        'STF-IMP,2026-08-26,absent,,CSV import',
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('hr.attendance.import'), ['file' => $upload])
        ->assertRedirect();

    expect(StaffAttendance::query()->where('staff_profile_id', $other->id)->sole()->status)->toBe(StaffAttendanceStatus::Absent)
        ->and(app(ImportStaffAttendanceCsvAction::class))->toBeInstanceOf(ImportStaffAttendanceCsvAction::class);
});

it('lets staff check in when the setting is on and records the IP', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $user = User::factory()->create();
    $staff = makeStaffProfile(['user_id' => $user->id]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('portal.staff-check-in.store'))
        ->assertSessionHasErrors('check_in');

    DB::table('settings')->where('key', 'hr.staff_self_checkin')->update(['value' => '1']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('portal.staff-check-in.store'))
        ->assertRedirect();

    $row = StaffAttendance::query()->where('staff_profile_id', $staff->id)->sole();
    expect($row->status)->toBe(StaffAttendanceStatus::Present)
        ->and($row->source)->toBe(StaffAttendanceSource::Self)
        ->and($row->academic_year_id)->toBe($year->id)
        ->and($row->remarks)->toContain('IP ')
        ->and($row->check_in)->not->toBeNull();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.staff-check-in'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/StaffCheckIn')
            ->where('enabled', true)
            ->has('rows', 1)
        );
});

it('summarizes a month and reports late and absence patterns', function () {
    $admin = actingPeopleAdmin(['hr.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile(['department' => 'Arabic']);
    $writer = app(StaffAttendanceWriterInterface::class);

    $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-03',
        status: StaffAttendanceStatus::Present,
        source: StaffAttendanceSource::Manual,
    ));
    $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-04',
        status: StaffAttendanceStatus::Late,
        source: StaffAttendanceSource::Manual,
        minutesLate: 15,
    ));
    $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-05',
        status: StaffAttendanceStatus::Absent,
        source: StaffAttendanceSource::Manual,
    ));

    $summary = app(SummarizeStaffAttendanceMonthAction::class)->execute($staff->id, 2026, 8);
    expect($summary['present'])->toBe(1)
        ->and($summary['late'])->toBe(1)
        ->and($summary['absent'])->toBe(1)
        ->and($summary['minutes_late'])->toBe(15)
        ->and($summary['working_days'])->toBe(3);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.attendance.reports', ['from' => '2026-08-01', 'to' => '2026-08-31', 'department' => 'Arabic']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Attendance/Reports')
            ->has('late', 1)
            ->has('absence', 1)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.attendance.reports.export', ['kind' => 'late', 'from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('15');
});

it('forbids staff attendance screens without hr.manage', function () {
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('hr.attendance.index'))
        ->assertForbidden();
});
