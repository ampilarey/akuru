<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ListCalendarHolidaysAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\StaffAttendance;
use Illuminate\Validation\ValidationException;

class RecordStaffAttendanceAction implements StaffAttendanceWriterInterface
{
    /** @var array<int, list<string>> */
    private array $holidayDates = [];

    public function record(StaffAttendanceDTO $dto): StaffAttendance
    {
        if (app(IsPayrollMonthLockedAction::class)->execute($dto->date)) {
            throw ValidationException::withMessages([
                'date' => 'Payroll for this month is locked. Record the change next period.',
            ]);
        }

        $existing = StaffAttendance::query()
            ->where('staff_profile_id', $dto->staffProfileId)
            ->whereDate('date', $dto->date)
            ->first();

        $status = $dto->status;

        if ($existing?->status === StaffAttendanceStatus::OnLeave && $status !== StaffAttendanceStatus::OnLeave) {
            return $existing;
        }

        if ($status !== StaffAttendanceStatus::OnLeave && $this->isHoliday($dto->academicYearId, $dto->date)) {
            $status = StaffAttendanceStatus::Holiday;
        }

        $payload = [
            'staff_profile_id' => $dto->staffProfileId,
            'academic_year_id' => $dto->academicYearId,
            'date' => $dto->date,
            'check_in' => $dto->checkIn,
            'check_out' => $dto->checkOut,
            'status' => $status,
            'source' => $dto->source,
            'minutes_late' => $dto->minutesLate,
            'marked_by' => $dto->markedBy,
            'remarks' => $dto->remarks,
        ];

        if ($existing === null) {
            return StaffAttendance::query()->create($payload);
        }

        $existing->fill($payload);
        $existing->save();

        return $existing->refresh();
    }

    private function isHoliday(int $academicYearId, string $date): bool
    {
        if (! isset($this->holidayDates[$academicYearId])) {
            $this->holidayDates[$academicYearId] = app(ListCalendarHolidaysAction::class)
                ->execute($academicYearId)
                ->pluck('date')
                ->filter()
                ->values()
                ->all();
        }

        return in_array($date, $this->holidayDates[$academicYearId], true);
    }
}
