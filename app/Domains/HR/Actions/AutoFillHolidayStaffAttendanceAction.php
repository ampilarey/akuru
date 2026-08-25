<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ListCalendarHolidaysAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\People\Actions\ListStaffProfilesAction;

class AutoFillHolidayStaffAttendanceAction
{
    public function __construct(private StaffAttendanceWriterInterface $writer) {}

    public function execute(int $academicYearId, ?int $markedBy = null): int
    {
        $holidays = app(ListCalendarHolidaysAction::class)->execute($academicYearId);
        $staff = app(ListStaffProfilesAction::class)->execute(['status' => 'active']);
        $written = 0;

        foreach ($holidays as $holiday) {
            $date = (string) ($holiday['date'] ?? '');
            if ($date === '') {
                continue;
            }

            foreach ($staff as $profile) {
                $this->writer->record(new StaffAttendanceDTO(
                    staffProfileId: (int) $profile->id,
                    academicYearId: $academicYearId,
                    date: $date,
                    status: StaffAttendanceStatus::Holiday,
                    source: StaffAttendanceSource::Manual,
                    markedBy: $markedBy,
                    remarks: (string) ($holiday['title'] ?? 'Holiday'),
                ));
                $written++;
            }
        }

        return $written;
    }
}
