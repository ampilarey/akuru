<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use Illuminate\Validation\ValidationException;

class SelfCheckInStaffAttendanceAction
{
    public function __construct(private StaffAttendanceWriterInterface $writer) {}

    public function execute(int $userId, ?string $ip = null): array
    {
        $settings = app(ResolveHrSettingsAction::class)->execute();
        if (! $settings['staff_self_checkin']) {
            throw ValidationException::withMessages([
                'check_in' => 'Staff self check-in is disabled.',
            ]);
        }

        $profile = app(ResolveStaffProfileForUserAction::class)->execute($userId);
        if ($profile === null) {
            throw ValidationException::withMessages([
                'check_in' => 'No staff profile is linked to this account.',
            ]);
        }

        $date = now('Indian/Maldives')->toDateString();
        $year = app(ResolveAcademicYearForDateAction::class)->execute($date);
        if ($year === null) {
            throw ValidationException::withMessages([
                'check_in' => 'No academic year covers today.',
            ]);
        }

        $row = $this->writer->record(new StaffAttendanceDTO(
            staffProfileId: (int) $profile['id'],
            academicYearId: (int) $year['id'],
            date: $date,
            status: StaffAttendanceStatus::Present,
            source: StaffAttendanceSource::Self,
            markedBy: $userId,
            checkIn: now('Indian/Maldives')->format('H:i:s'),
            remarks: $ip ? 'IP '.$ip : null,
        ));

        return [
            'id' => $row->id,
            'date' => $row->date?->toDateString(),
            'status' => $row->status?->value ?? $row->status,
            'check_in' => $row->check_in,
            'remarks' => $row->remarks,
        ];
    }
}
