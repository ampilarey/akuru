<?php

namespace App\Console\Commands;

use App\Support\Services\Backup\BackupSettings;
use Illuminate\Console\Command;

/** BK1: `php artisan akuru:backup-list` — the copies held here and off-site. */
class BackupListCommand extends Command
{
    protected $signature = 'akuru:backup-list';

    protected $description = 'List the backups kept on this server and off-site';

    public function handle(BackupSettings $settings): int
    {
        foreach (array_filter([$settings->local(), $settings->offsite()]) as $store) {
            $this->line($store->describe().':');
            $names = $store->names();
            if ($names === []) {
                $this->line('  (none)');
            }
            foreach ($names as $name) {
                $this->line(sprintf('  %s  %s MB', $name, number_format(((int) $store->size($name)) / 1048576, 1)));
            }
        }

        return self::SUCCESS;
    }
}
