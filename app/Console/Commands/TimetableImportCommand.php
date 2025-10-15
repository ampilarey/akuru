<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Timetable;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\ClassRoom;
use App\Models\Period;
use Illuminate\Support\Facades\Storage;

class TimetableImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timetable:import 
                            {--file=storage/app/imports/timetable.csv : Path to the CSV file}
                            {--dry-run : Run without making changes}
                            {--create-periods : Create periods if they don\'t exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import timetable entries from CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->option('file');
        $dryRun = $this->option('dry-run');
        $createPeriods = $this->option('create-periods');

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting timetable import from: {$filePath}");
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }

        // Read CSV file
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Could not open file: {$filePath}");
            return 1;
        }

        // Read header
        $header = fgetcsv($handle);
        $expectedHeaders = ['classroom', 'subject', 'teacher', 'day_of_week', 'period', 'start', 'end', 'room'];
        
        if (!$header || array_diff($expectedHeaders, $header)) {
            $this->error("Invalid CSV format. Expected headers: " . implode(', ', $expectedHeaders));
            fclose($handle);
            return 1;
        }

        $processed = 0;
        $errors = 0;
        $created = 0;

        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $processed++;

            try {
                // Find or create entities
                $classroom = $this->findClassroom($data['classroom']);
                $subject = $this->findSubject($data['subject']);
                $teacher = $this->findTeacher($data['teacher']);
                $period = $this->findOrCreatePeriod($data['period'], $data['start'], $data['end'], $createPeriods);

                if (!$classroom) {
                    throw new \Exception("Classroom not found: {$data['classroom']}");
                }
                if (!$subject) {
                    throw new \Exception("Subject not found: {$data['subject']}");
                }
                if (!$teacher) {
                    throw new \Exception("Teacher not found: {$data['teacher']}");
                }
                if (!$period) {
                    throw new \Exception("Period not found: {$data['period']}");
                }

                // Create timetable entry
                if (!$dryRun) {
                    $timetableData = [
                        'class_id' => $classroom->id,
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacher->id,
                        'period_id' => $period->id,
                        'day_of_week' => $this->parseDayOfWeek($data['day_of_week']),
                        'room' => $data['room'] ?? null,
                    ];

                    // Check if entry already exists
                    $exists = Timetable::where($timetableData)->exists();
                    if (!$exists) {
                        Timetable::create($timetableData);
                        $created++;
                    } else {
                        $this->warn("Entry already exists for row {$processed}");
                    }
                } else {
                    $this->info("Would create: {$data['classroom']} - {$data['subject']} - {$data['teacher']} - {$data['day_of_week']} - {$data['period']}");
                    $created++;
                }

            } catch (\Exception $e) {
                $errors++;
                $this->error("Error processing row {$processed}: " . $e->getMessage());
            }
        }

        fclose($handle);

        // Summary
        $this->info("\nImport Summary:");
        $this->info("Processed: {$processed} rows");
        $this->info("Created: {$created} entries");
        $this->info("Errors: {$errors}");

        if ($dryRun) {
            $this->warn("DRY RUN completed - no changes were made");
        } else {
            $this->info("Import completed successfully!");
        }

        return 0;
    }

    private function findClassroom(string $name): ?ClassRoom
    {
        return ClassRoom::where('name', 'LIKE', "%{$name}%")->first();
    }

    private function findSubject(string $name): ?Subject
    {
        return Subject::where('name', 'LIKE', "%{$name}%")->first();
    }

    private function findTeacher(string $name): ?Teacher
    {
        return Teacher::whereHas('user', function($query) use ($name) {
            $query->where('name', 'LIKE', "%{$name}%");
        })->first();
    }

    private function findOrCreatePeriod(string $name, string $start, string $end, bool $create): ?Period
    {
        $period = Period::where('name', $name)->first();
        
        if (!$period && $create) {
            $period = Period::create([
                'name' => $name,
                'start_time' => $start,
                'end_time' => $end,
            ]);
            $this->info("Created new period: {$name} ({$start} - {$end})");
        }

        return $period;
    }

    private function parseDayOfWeek(string $day): int
    {
        $days = [
            'monday' => 1, 'mon' => 1,
            'tuesday' => 2, 'tue' => 2,
            'wednesday' => 3, 'wed' => 3,
            'thursday' => 4, 'thu' => 4,
            'friday' => 5, 'fri' => 5,
            'saturday' => 6, 'sat' => 6,
            'sunday' => 0, 'sun' => 0,
        ];

        return $days[strtolower($day)] ?? 1;
    }
}