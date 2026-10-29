<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Identity\Actions\ListAdminUsersAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Settings\Models\BackupRun;
use App\Support\Said;

/**
 * BK2 (STATUS §5ur): a backup that failed, or was kept on this server only,
 * is told to every super admin — the people who can open System Settings —
 * in their own language, in a category nobody can switch off.
 */
class NotifyBackupFailureAction
{
    public function execute(BackupRun $run): int
    {
        if (! in_array($run->status, ['failed', 'partial'], true)) {
            return 0;
        }
        $why = mb_substr((string) ($run->error ?? $run->offsite_error ?? '—'), 0, 300);
        $title = Said::of($run->status === 'failed' ? 'admin.backup_failed_title' : 'admin.backup_partial_title');
        $body = Said::of($run->status === 'failed' ? 'admin.backup_failed_body' : 'admin.backup_partial_body', ['why' => $why]);
        $sent = 0;
        foreach (app(ListAdminUsersAction::class)->query(['role' => 'super_admin'])->pluck('id') as $id) {
            app(SendUserNotificationAction::class)->execute((int) $id, $title, $body, ['category' => 'system', 'href' => '/admin/settings']);
            $sent++;
        }

        return $sent;
    }
}
