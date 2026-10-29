<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\BackupRun;
use App\Support\Services\Backup\BackupSettings;

/**
 * BK2 (STATUS §5ur): the backups, as System Settings shows them — the last
 * run, the last good one, and whether that is too long ago. A night missed
 * (the cron stopped, the run failed) turns the card red after 26 hours.
 */
class ResolveBackupStatusAction
{
    public const STALE_HOURS = 26;

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $settings = app(BackupSettings::class);
        $last = BackupRun::query()->latest('started_at')->latest('id')->first();
        $good = BackupRun::query()->whereIn('status', ['succeeded', 'partial'])->latest('finished_at')->first();
        $enabled = (bool) config('backup.enabled', true);

        return [
            'enabled' => $enabled,
            'offsite_configured' => $settings->offsite() !== null,
            'uses_app_key' => $settings->usesAppKey(),
            'stale' => $enabled && ($good === null || $good->finished_at === null || $good->finished_at->lt(now()->subHours(self::STALE_HOURS))),
            'last_good' => $good === null ? null : [
                'name' => $good->name,
                'at' => $good->finished_at?->timezone('Indian/Maldives')->format('Y-m-d H:i'),
                'size_mb' => round(((int) $good->size_bytes) / 1048576, 1),
                'offsite' => (bool) $good->offsite,
            ],
            'last' => $last === null ? null : [
                'status' => $last->status,
                'at' => ($last->finished_at ?? $last->started_at)?->timezone('Indian/Maldives')->format('Y-m-d H:i'),
                'error' => $last->error ?? $last->offsite_error,
            ],
        ];
    }
}
