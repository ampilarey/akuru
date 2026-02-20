<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptStudentIdsCommand extends Command
{
    protected $signature   = 'students:encrypt-ids {--dry-run : Show what would be updated without saving}';
    protected $description = 'Re-encrypt plaintext national_id and passport values in registration_students table';

    public function handle(): int
    {
        $rows = DB::table('registration_students')
            ->whereNotNull('national_id')
            ->orWhereNotNull('passport')
            ->get(['id', 'national_id', 'passport']);

        $updated = 0;

        foreach ($rows as $row) {
            $updates = [];

            foreach (['national_id', 'passport'] as $field) {
                $value = $row->$field;
                if ($value === null) {
                    continue;
                }
                // Check if already encrypted (Laravel encrypted values start with "eyJ")
                if (str_starts_with($value, 'eyJ')) {
                    continue;
                }
                $updates[$field] = Crypt::encrypt($value);
            }

            if (empty($updates)) {
                continue;
            }

            $this->line("Row {$row->id}: " . implode(', ', array_keys($updates)) . " will be encrypted");

            if (! $this->option('dry-run')) {
                DB::table('registration_students')->where('id', $row->id)->update($updates);
                $updated++;
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. Use without --dry-run to apply.');
        } else {
            $this->info("Encrypted {$updated} record(s).");
        }

        return 0;
    }
}
