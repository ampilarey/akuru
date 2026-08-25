<?php

use App\Domains\Finance\Models\PayrollPosting;
use App\Domains\HR\Actions\ApprovePayrollPeriodAction;
use App\Domains\HR\Actions\ApproveStaffLeaveAction;
use App\Domains\HR\Actions\LockPayrollPeriodAction;
use App\Domains\HR\Actions\MaldivesPayrollCalculator;
use App\Domains\HR\Actions\MarkPayrollPaidAction;
use App\Domains\HR\Actions\ResolvePayrollSettingsAction;
use App\Domains\HR\Actions\RunPayrollAction;
use App\Domains\HR\Actions\SaveStaffContractAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\PayslipStatus;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Enums\StaffContractType;
use App\Domains\HR\Models\LeaveType;
use App\Domains\HR\Models\Payslip;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function enablePayroll(): void
{
    config()->set('payroll.enabled', true);
    DB::table('settings')->where('key', 'payroll.enabled')->update(['value' => '1']);
}

it('calculates a known Maldives payslip to two decimal places', function () {
    $result = app(MaldivesPayrollCalculator::class)->calculate([
        'basic_salary' => 10000,
        'allowances' => [['name' => 'Housing', 'amount' => 1000]],
        'unpaid_days' => 2,
        'working_days' => 22,
        'employee_pension_rate' => 0.07,
        'employer_pension_rate' => 0.07,
        'tax_brackets' => [
            ['up_to' => 60000, 'rate' => 0],
            ['up_to' => 100000, 'rate' => 0.08],
            ['up_to' => null, 'rate' => 0.15],
        ],
    ]);

    expect($result['gross'])->toBe(11000.0)
        ->and($result['unpaid_leave_deduction'])->toBe(909.09)
        ->and($result['employee_pension'])->toBe(770.0)
        ->and($result['employer_pension'])->toBe(770.0)
        ->and($result['tax_withheld'])->toBe(0.0)
        ->and($result['net_pay'])->toBe(9320.91);
});

it('runs payroll idempotently, locks inputs, and posts into Finance', function () {
    enablePayroll();
    $year = makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();
    app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'contract_type' => StaffContractType::Permanent->value,
        'start_date' => '2026-01-01',
        'basic_salary' => 10000,
        'allowances' => [['name' => 'Housing', 'amount' => 1000]],
    ]);

    app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'leave_type_id' => LeaveType::query()->where('code', 'unpaid')->value('id'),
        'from_date' => '2026-08-03',
        'to_date' => '2026-08-04',
    ]);

    $runner = actingPeopleAdmin(['payroll.run']);
    $period = app(RunPayrollAction::class)->execute(2026, 8, $runner->id);
    $again = app(RunPayrollAction::class)->execute(2026, 8, $runner->id);

    expect($period->id)->toBe($again->id)
        ->and(Payslip::query()->count())->toBe(1);

    $payslip = Payslip::query()->sole();
    expect((float) $payslip->gross)->toBe(11000.0)
        ->and((float) $payslip->unpaid_leave_deduction)->toBe(909.09)
        ->and((float) $payslip->net_pay)->toBe(9320.91)
        ->and($payslip->status)->toBe(PayslipStatus::Draft)
        ->and($year->id)->toBeInt();

    $approver = actingPeopleAdmin(['payroll.approve']);
    app(ApprovePayrollPeriodAction::class)->execute($period->id, $approver->id);
    expect(Payslip::query()->sole()->status)->toBe(PayslipStatus::Final);

    app(RunPayrollAction::class)->execute(2026, 8, $runner->id);
    expect((float) Payslip::query()->sole()->net_pay)->toBe(9320.91)
        ->and(Payslip::query()->count())->toBe(1);

    app(MarkPayrollPaidAction::class)->execute($period->id);
    expect(PayrollPosting::query()->where('year', 2026)->where('month', 8)->exists())->toBeTrue()
        ->and(Payslip::query()->sole()->document_id)->not->toBeNull();

    app(LockPayrollPeriodAction::class)->execute($period->id);

    $writer = app(StaffAttendanceWriterInterface::class);
    expect(fn () => $writer->record(new StaffAttendanceDTO(
        staffProfileId: $staff->id,
        academicYearId: $year->id,
        date: '2026-08-10',
        status: StaffAttendanceStatus::Present,
        source: StaffAttendanceSource::Manual,
    )))->toThrow(ValidationException::class);
});

it('prorates a mid-month join and separates payroll.run from payroll.approve', function () {
    enablePayroll();
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();
    app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'contract_type' => StaffContractType::FixedTerm->value,
        'start_date' => '2026-08-16',
        'basic_salary' => 31000,
    ]);

    $runner = actingPeopleAdmin(['payroll.run']);
    $period = app(RunPayrollAction::class)->execute(2026, 8, $runner->id);
    $payslip = Payslip::query()->sole();
    expect((float) $payslip->inputs['proration'])->toBe(round(16 / 31, 4));

    $this->withoutLocalizationMiddleware()
        ->actingAs($runner)
        ->post(route('hr.payroll.approve', $period))
        ->assertForbidden();

    $approver = actingPeopleAdmin(['payroll.approve']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($approver)
        ->get(route('hr.payroll.index'))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($runner)
        ->get(route('hr.payroll.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('HR/Payroll/Index')->has('rows', 1));

    $approver = actingPeopleAdmin(['payroll.approve']);
    app(ApprovePayrollPeriodAction::class)->execute($period->id, $approver->id);

    $export = $this->withoutLocalizationMiddleware()
        ->actingAs($runner)
        ->get(route('hr.payroll.export', $period));
    $export->assertOk();
    expect($export->streamedContent())->toContain('net_pay')
        ->and($export->streamedContent())->toContain((string) $staff->id);

    $staffUser = User::query()->findOrFail($staff->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($staffUser)
        ->get(route('portal.payslips'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Payslips')
            ->has('rows', 1)
            ->where('rows.0.staff_profile_id', $staff->id)
        );
});

it('keeps payroll screens off when the feature flag is down', function () {
    $admin = actingPeopleAdmin(['payroll.run']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.payroll.index'))
        ->assertForbidden();
});

it('stays off when only the settings row is enabled', function () {
    DB::table('settings')->where('key', 'payroll.enabled')->update(['value' => '1']);
    config()->set('payroll.enabled', false);

    expect(app(ResolvePayrollSettingsAction::class)->execute()['enabled'])->toBeFalse();

    $admin = actingPeopleAdmin(['payroll.run']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.payroll.index'))
        ->assertForbidden();
});

it('stays off when only PAYROLL_ENABLED config is on', function () {
    config()->set('payroll.enabled', true);

    expect(app(ResolvePayrollSettingsAction::class)->execute()['enabled'])->toBeFalse();
});

it('applies updated rates only to the next period', function () {
    enablePayroll();
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();
    app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'contract_type' => StaffContractType::Permanent->value,
        'start_date' => '2026-01-01',
        'basic_salary' => 10000,
    ]);

    $runner = actingPeopleAdmin(['payroll.run']);
    app(RunPayrollAction::class)->execute(2026, 7, $runner->id);
    $july = Payslip::query()->whereHas('period', fn ($query) => $query->where('year', 2026)->where('month', 7))->sole();
    expect((float) $july->employee_pension)->toBe(700.0);

    DB::table('settings')->where('key', 'payroll.rules')->update([
        'value' => json_encode([
            'employee_pension_rate' => 0.08,
            'employer_pension_rate' => 0.08,
            'working_days' => 22,
            'rounding' => 'half_up_2',
            'tax_brackets' => [
                ['up_to' => 60000, 'rate' => 0],
                ['up_to' => 100000, 'rate' => 0.08],
                ['up_to' => null, 'rate' => 0.15],
            ],
        ]),
    ]);

    app(RunPayrollAction::class)->execute(2026, 8, $runner->id);
    $august = Payslip::query()->whereHas('period', fn ($query) => $query->where('year', 2026)->where('month', 8))->sole();

    expect((float) $july->refresh()->employee_pension)->toBe(700.0)
        ->and((float) $august->employee_pension)->toBe(800.0)
        ->and((float) $august->net_pay)->toBe(9200.0);
});

it('withholds tax from the configured brackets', function () {
    $result = app(MaldivesPayrollCalculator::class)->calculate([
        'basic_salary' => 80000,
        'allowances' => [],
        'unpaid_days' => 0,
        'working_days' => 22,
        'employee_pension_rate' => 0.07,
        'employer_pension_rate' => 0.07,
        'tax_brackets' => [
            ['up_to' => 60000, 'rate' => 0],
            ['up_to' => 100000, 'rate' => 0.08],
            ['up_to' => null, 'rate' => 0.15],
        ],
    ]);

    expect($result['gross'])->toBe(80000.0)
        ->and($result['tax_withheld'])->toBe(1600.0)
        ->and($result['employee_pension'])->toBe(5600.0)
        ->and($result['net_pay'])->toBe(72800.0);
});

it('lets staff open only their own payslip document', function () {
    enablePayroll();
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $owner = makeStaffProfile();
    $other = makeStaffProfile();
    app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $owner->id,
        'contract_type' => StaffContractType::Permanent->value,
        'start_date' => '2026-01-01',
        'basic_salary' => 10000,
    ]);

    $runner = actingPeopleAdmin(['payroll.run']);
    $period = app(RunPayrollAction::class)->execute(2026, 8, $runner->id);
    $approver = actingPeopleAdmin(['payroll.approve']);
    app(ApprovePayrollPeriodAction::class)->execute($period->id, $approver->id);
    app(MarkPayrollPaidAction::class)->execute($period->id);

    $payslip = Payslip::query()->sole();
    $ownerUser = User::query()->findOrFail($owner->user_id);
    $otherUser = User::query()->findOrFail($other->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ownerUser)
        ->get(route('hr.payslips.document', $payslip))
        ->assertOk();

    $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->get(route('hr.payslips.document', $payslip))
        ->assertForbidden();
});
