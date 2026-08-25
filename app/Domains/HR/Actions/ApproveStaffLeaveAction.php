<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveStaffLeaveAction
{
    public function __construct(private StaffAttendanceWriterInterface $writer) {}

    /**
     * @param  array{staff_profile_id: int, leave_type_id: int, from_date: string, to_date: string, half_day?: bool, request_id?: int|null, marked_by?: int|null}  $data
     */
    public function execute(array $data): array
    {
        $type = LeaveType::query()->find((int) $data['leave_type_id']);
        if ($type === null) {
            throw ValidationException::withMessages(['leave_type_id' => 'Unknown leave type.']);
        }

        $from = (string) $data['from_date'];
        $to = (string) ($data['to_date'] ?? $from);
        $halfDay = (bool) ($data['half_day'] ?? false);
        $days = app(CountLeaveDaysAction::class)->execute($from, $to, $halfDay);
        if ($days <= 0) {
            throw ValidationException::withMessages(['from_date' => 'Leave dates are invalid.']);
        }

        $year = app(ResolveAcademicYearForDateAction::class)->execute($from);
        if ($year === null) {
            throw ValidationException::withMessages(['from_date' => 'No academic year covers this leave.']);
        }

        $lock = app(IsPayrollMonthLockedAction::class);
        for ($cursor = Carbon::parse($from, 'Indian/Maldives')->startOfDay(); $cursor->lte(Carbon::parse($to, 'Indian/Maldives')->startOfDay()); $cursor->addDay()) {
            if ($lock->execute($cursor->toDateString())) {
                throw ValidationException::withMessages([
                    'from_date' => 'Payroll for '.$cursor->format('Y-m').' is locked. Record the change next period.',
                ]);
            }
        }

        return DB::transaction(function () use ($data, $type, $from, $to, $halfDay, $days, $year): array {
            $entitlement = app(EnsureLeaveEntitlementAction::class)->execute(
                (int) $data['staff_profile_id'],
                (int) $type->id,
                (int) $year['id'],
            );

            if ($type->paid) {
                $balance = app(LeaveBalanceCalculator::class)->execute($entitlement->id);
                if ($balance < $days) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => 'Insufficient leave balance ('.$balance.' remaining, '.$days.' requested).',
                    ]);
                }
            }

            app(AppendLeaveLedgerAction::class)->execute(
                $entitlement->id,
                -1 * $days,
                'taken',
                isset($data['request_id']) ? (int) $data['request_id'] : null,
            );

            $start = Carbon::parse($from, 'Indian/Maldives')->startOfDay();
            $end = Carbon::parse($to, 'Indian/Maldives')->startOfDay();
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $this->writer->record(new StaffAttendanceDTO(
                    staffProfileId: (int) $data['staff_profile_id'],
                    academicYearId: (int) $year['id'],
                    date: $date->toDateString(),
                    status: StaffAttendanceStatus::OnLeave,
                    source: StaffAttendanceSource::Manual,
                    markedBy: isset($data['marked_by']) ? (int) $data['marked_by'] : null,
                    remarks: $type->paid
                        ? ($halfDay ? 'Half-day leave' : 'Approved leave')
                        : ($halfDay ? 'Half-day unpaid leave' : 'Approved unpaid leave'),
                ));
            }

            return [
                'entitlement_id' => $entitlement->id,
                'days' => $days,
                'balance' => app(LeaveBalanceCalculator::class)->execute($entitlement->id),
            ];
        });
    }
}
