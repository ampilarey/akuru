<?php

namespace App\Support\Services\Backup;

use App\Support\Contracts\BackupStoreInterface;
use Illuminate\Support\Facades\Storage;

/** BK1: the backup's configuration, read in one place. */
final class BackupSettings
{
    public function local(): LocalBackupStore
    {
        return new LocalBackupStore((string) config('backup.local_path'));
    }

    /** The store off this server, or null while none is configured. */
    public function offsite(): ?BackupStoreInterface
    {
        $disk = (string) config('backup.disk', '');

        return $disk === '' ? null : new DiskBackupStore(Storage::disk($disk), 'off-site ('.$disk.')');
    }

    public function passphrase(): string
    {
        return (string) (config('backup.passphrase') ?: config('app.key'));
    }

    public function usesAppKey(): bool
    {
        return (string) config('backup.passphrase', '') === '';
    }

    public function binary(string $name): string
    {
        return (string) config('backup.binaries.'.$name, $name);
    }

    public function filesRoot(): string
    {
        return rtrim((string) config('backup.files_root'), '/');
    }

    /** @return list<string> */
    public function filesExclude(): array
    {
        return array_values((array) config('backup.files_exclude', []));
    }

    /** @return list<string> */
    public function mysqldumpOptions(): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) config('backup.mysqldump_options', ''))) ?: []));
    }

    /** @return array<string, mixed> the default connection's settings */
    public function database(): array
    {
        return (array) config('database.connections.'.config('database.default'));
    }

    /** @return list<string> host, port or socket, and user — never the password */
    public function connectionArgs(): array
    {
        $db = $this->database();
        $args = ['--user='.$db['username']];
        if (! empty($db['unix_socket'])) {
            $args[] = '--socket='.$db['unix_socket'];
        } else {
            $args[] = '--host='.$db['host'];
            $args[] = '--port='.$db['port'];
        }

        return $args;
    }

    public function workDir(string $name): string
    {
        $dir = storage_path('app/backup-work/'.$name.'-'.bin2hex(random_bytes(4)));
        mkdir($dir, 0700, true);

        return $dir;
    }
}
