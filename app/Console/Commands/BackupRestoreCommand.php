<?php

namespace App\Console\Commands;

use App\Support\Services\Backup\BackupRestorer;
use App\Support\Services\Backup\BackupSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * BK1: `php artisan akuru:backup-restore akuru-20261029-030000.akb --into=akuru_restore`
 * puts a backup's database into another database, to check it or to copy
 * from. Into the live database only with --into-live, and on production
 * only after typing the database's name. `--files=<folder>` puts the
 * uploaded files into a folder. Steps: `docs/deployment.md`.
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'akuru:backup-restore {name : The backup file, e.g. akuru-20261029-030000.akb}
        {--into= : The database to restore into (it must exist)}
        {--into-live : Restore into the live database itself}
        {--files= : A folder to put the uploaded files into}';

    protected $description = 'Restore a backup into a database (and its files into a folder)';

    public function handle(BackupRestorer $restorer, BackupSettings $settings): int
    {
        $live = (string) $settings->database()['database'];
        $into = $this->option('into-live') ? $live : (string) $this->option('into');
        if ($into === '' && ! $this->option('files')) {
            $this->error('Say where: --into=<database>, --into-live, or --files=<folder>.');

            return self::INVALID;
        }
        if ($into === $live && ! $this->option('into-live')) {
            $this->error('That is the live database. Use --into-live if you mean it.');

            return self::INVALID;
        }
        if ($into === $live && app()->environment('production')
            && $this->ask('This replaces the live database. Type its name ('.$live.') to go on') !== $live) {
            $this->error('Not restored.');

            return self::FAILURE;
        }

        try {
            $opened = $restorer->open((string) $this->argument('name'));
            try {
                if ($into !== '') {
                    $restorer->restoreDatabase($opened['work'], $into);
                    $this->info('Database restored into '.$into.' ('.($opened['manifest']['tables'] ?? '?').' tables, made '.($opened['manifest']['made_at'] ?? '?').').');
                }
                if ($this->option('files')) {
                    $restorer->restoreFiles($opened['work'], (string) $this->option('files'));
                    $this->info('Files restored into '.$this->option('files').'.');
                }
            } finally {
                $restorer->close($opened['work']);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
