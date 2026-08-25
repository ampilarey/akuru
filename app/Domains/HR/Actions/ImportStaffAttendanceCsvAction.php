<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use Illuminate\Validation\ValidationException;

class ImportStaffAttendanceCsvAction
{
    public function __construct(private StaffAttendanceWriterInterface $writer) {}

    /**
     * @return array{imported: int}
     */
    public function execute(string $csv, ?int $markedBy = null): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        if ($lines === [] || trim((string) $lines[0]) === '') {
            throw ValidationException::withMessages(['file' => 'CSV is empty.']);
        }

        $header = array_map(
            fn (string $column): string => strtolower(trim($column)),
            str_getcsv((string) array_shift($lines)) ?: []
        );

        $imported = 0;
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);
            $row = [];
            foreach ($header as $offset => $key) {
                $row[$key] = $cells[$offset] ?? null;
            }

            $profile = $this->resolveProfile($row);
            if ($profile === null) {
                throw ValidationException::withMessages([
                    'file' => 'Unknown staff on row '.($index + 2).'.',
                ]);
            }

            $date = (string) ($row['date'] ?? '');
            $status = StaffAttendanceStatus::tryFrom((string) ($row['status'] ?? ''));
            if ($date === '' || $status === null) {
                throw ValidationException::withMessages([
                    'file' => 'Invalid date or status on row '.($index + 2).'.',
                ]);
            }

            $year = app(ResolveAcademicYearForDateAction::class)->execute($date);
            if ($year === null) {
                throw ValidationException::withMessages([
                    'file' => 'No academic year covers '.$date.'.',
                ]);
            }

            $this->writer->record(new StaffAttendanceDTO(
                staffProfileId: (int) $profile['id'],
                academicYearId: (int) $year['id'],
                date: $date,
                status: $status,
                source: StaffAttendanceSource::Import,
                markedBy: $markedBy,
                minutesLate: isset($row['minutes_late']) && $row['minutes_late'] !== ''
                    ? (int) $row['minutes_late']
                    : null,
                remarks: $row['remarks'] !== null && $row['remarks'] !== '' ? (string) $row['remarks'] : null,
            ));
            $imported++;
        }

        return ['imported' => $imported];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function resolveProfile(array $row): ?array
    {
        $resolver = app(ResolveStaffProfileForUserAction::class);

        if (! empty($row['staff_profile_id'])) {
            return $resolver->executeById((int) $row['staff_profile_id']);
        }

        if (! empty($row['staff_number'])) {
            return $resolver->executeByStaffNumber((string) $row['staff_number']);
        }

        return null;
    }
}
