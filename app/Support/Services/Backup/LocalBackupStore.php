<?php

namespace App\Support\Services\Backup;

use App\Support\Contracts\BackupStoreInterface;
use RuntimeException;

/** BK1: backups in a folder on this server. */
final class LocalBackupStore implements BackupStoreInterface
{
    public function __construct(private string $dir) {}

    public function put(string $localPath, string $name): void
    {
        $this->ensureDir();
        $target = $this->path($name);
        if (realpath($localPath) === realpath($target)) {
            return;
        }
        if (! copy($localPath, $target)) {
            throw new RuntimeException('Could not copy the backup into '.$this->dir);
        }
        @chmod($target, 0600);
    }

    public function get(string $name, string $localPath): void
    {
        if (! is_file($this->path($name)) || ! copy($this->path($name), $localPath)) {
            throw new RuntimeException('No backup named '.$name.' here.');
        }
    }

    public function names(): array
    {
        if (! is_dir($this->dir)) {
            return [];
        }
        $names = array_values(array_map('basename', glob($this->dir.'/*'.BackupNames::EXTENSION) ?: []));
        sort($names);

        return $names;
    }

    public function delete(string $name): void
    {
        @unlink($this->path($name));
    }

    public function size(string $name): ?int
    {
        return is_file($this->path($name)) ? (int) filesize($this->path($name)) : null;
    }

    public function describe(): string
    {
        return 'this server';
    }

    public function path(string $name): string
    {
        return rtrim($this->dir, '/').'/'.basename($name);
    }

    private function ensureDir(): void
    {
        if (! is_dir($this->dir) && ! mkdir($this->dir, 0700, true) && ! is_dir($this->dir)) {
            throw new RuntimeException('Could not make '.$this->dir);
        }
    }
}
