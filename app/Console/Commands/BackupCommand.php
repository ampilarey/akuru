<?php

namespace App\Console\Commands;

use App\Domains\Settings\Actions\RunBackupAction;
use Illuminate\Console\Command;

/** SPEC "Backups" (BK1): `php artisan akuru:backup` — the nightly run, or one now. */
class BackupCommand extends Command
{
    protected $signature = 'akuru:backup';

    protected $description = 'Back up the database and uploaded files, encrypted, here and off-site';

    public function handle(RunBackupAction $action): int
    {
        $run = $action->execute();
        if ($run->status === 'failed') {
            $this->error('Backup failed: '.$run->error);

            return self::FAILURE;
        }
        $this->info(sprintf('Backup %s: %s, %s tables, %s.', $run->status, $run->name, $run->tables, number_format(((int) $run->size_bytes) / 1048576, 1).' MB'));
        if ($run->offsite_error !== null) {
            $this->warn('Kept on this server only; the off-site copy failed: '.$run->offsite_error);
        }

        return $run->status === 'succeeded' ? self::SUCCESS : self::FAILURE;
    }
}
