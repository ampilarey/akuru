<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\BackupRun;
use App\Support\Services\Backup\BackupMaker;
use App\Support\Services\Backup\BackupNames;
use App\Support\Services\Backup\BackupSettings;
use Throwable;

/**
 * SPEC "Backups" (BK1, STATUS §5up): the nightly run. Make the encrypted
 * file on this server; copy it off-site when a store is configured; then
 * keep the newest daily, weekly and monthly copies and remove the rest, in
 * both places. Every run is a row in `backup_runs`, failed ones with why.
 * An off-site copy that fails does not undo the local one: the run says so.
 */
class RunBackupAction
{
    public function __construct(private BackupSettings $settings, private BackupMaker $maker) {}

    public function execute(): BackupRun
    {
        $run = BackupRun::query()->create(['status' => 'running', 'started_at' => now()]);
        try {
            $made = $this->maker->make();
            $run->fill(['name' => $made['name'], 'size_bytes' => $made['size'], 'database_bytes' => $made['database_bytes'], 'files_bytes' => $made['files_bytes'], 'tables' => $made['tables']]);
            $offsite = $this->settings->offsite();
            if ($offsite !== null) {
                try {
                    $offsite->put($made['path'], $made['name']);
                    $run->offsite = true;
                } catch (Throwable $e) {
                    $run->offsite_error = mb_substr($e->getMessage(), 0, 500);
                }
            }
            $run->removed = $this->prune();
            $run->fill(['status' => $run->offsite_error === null ? 'succeeded' : 'partial', 'finished_at' => now()])->save();
        } catch (Throwable $e) {
            $run->fill(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();
        }

        return $run->refresh();
    }

    /** @return int the copies removed, here and off-site */
    private function prune(): int
    {
        $keep = (array) config('backup.keep');
        $removed = 0;
        foreach (array_filter([$this->settings->local(), $this->settings->offsite()]) as $store) {
            foreach (BackupNames::toDelete($store->names(), (int) $keep['daily'], (int) $keep['weekly'], (int) $keep['monthly']) as $name) {
                $store->delete($name);
                $removed++;
            }
        }

        return $removed;
    }
}
