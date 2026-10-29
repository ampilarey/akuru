<?php

namespace App\Support\Services\Backup;

use App\Support\Contracts\BackupStoreInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/** BK1: backups on a filesystem disk off this server (an S3-compatible store). */
final class DiskBackupStore implements BackupStoreInterface
{
    public function __construct(private Filesystem $disk, private string $label) {}

    public function put(string $localPath, string $name): void
    {
        $stream = fopen($localPath, 'rb');
        try {
            if ($this->disk->writeStream($name, $stream) === false) {
                throw new RuntimeException('The off-site store refused the backup.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function get(string $name, string $localPath): void
    {
        $in = $this->disk->readStream($name);
        if ($in === null) {
            throw new RuntimeException('No backup named '.$name.' off-site.');
        }
        $out = fopen($localPath, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        fclose($in);
    }

    public function names(): array
    {
        $names = array_values(array_filter($this->disk->files(), fn (string $f) => str_ends_with($f, BackupNames::EXTENSION)));
        sort($names);

        return $names;
    }

    public function delete(string $name): void
    {
        $this->disk->delete($name);
    }

    public function size(string $name): ?int
    {
        return $this->disk->exists($name) ? (int) $this->disk->size($name) : null;
    }

    public function describe(): string
    {
        return $this->label;
    }
}
