<?php

namespace App\Support\Services\Backup;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * BK1: opens a backup and puts it back — the database into a named
 * database (the live one only when the command is told so, twice), the
 * files into a folder. SPEC: a backup is valid only once a restore is
 * tested; `BackupRestoreTest` does that on every CI run.
 */
final class BackupRestorer
{
    public function __construct(private BackupSettings $settings) {}

    /**
     * @return array{work: string, manifest: array<string, mixed>}
     */
    public function open(string $name): array
    {
        $work = $this->settings->workDir('restore-'.pathinfo($name, PATHINFO_FILENAME));
        $local = $this->settings->local();
        $encrypted = $work.'/'.basename($name);
        if ($local->size($name) !== null) {
            $local->get($name, $encrypted);
        } elseif (($offsite = $this->settings->offsite()) !== null && $offsite->size($name) !== null) {
            $offsite->get($name, $encrypted);
        } else {
            throw new RuntimeException('No backup named '.$name.' here or off-site.');
        }
        BackupCipher::decrypt($encrypted, $work.'/bundle.tar', $this->settings->passphrase());
        @unlink($encrypted);
        $this->run([$this->settings->binary('tar'), '-xf', $work.'/bundle.tar', '-C', $work]);
        @unlink($work.'/bundle.tar');
        $manifest = json_decode((string) @file_get_contents($work.'/manifest.json'), true);
        if (! is_array($manifest) || ! is_file($work.'/database.sql.gz')) {
            throw new RuntimeException('The backup opened but holds no database.');
        }

        return ['work' => $work, 'manifest' => $manifest];
    }

    public function restoreDatabase(string $work, string $database): void
    {
        $db = $this->settings->database();
        $sql = fopen('compress.zlib://'.$work.'/database.sql.gz', 'rb');
        $process = new Process([$this->settings->binary('mysql'), ...$this->settings->connectionArgs(), '--default-character-set=utf8mb4', $database], null, ['MYSQL_PWD' => (string) $db['password']], $sql, 3600);
        $process->run();
        if (is_resource($sql)) {
            fclose($sql);
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysql failed: '.trim(mb_substr($process->getErrorOutput(), 0, 500)));
        }
    }

    public function restoreFiles(string $work, string $target): void
    {
        if (! is_dir($target) && ! mkdir($target, 0750, true) && ! is_dir($target)) {
            throw new RuntimeException('Could not make '.$target);
        }
        $this->run([$this->settings->binary('tar'), '-xzf', $work.'/files.tar.gz', '-C', $target]);
    }

    public function close(string $work): void
    {
        BackupMaker::removeDir($work);
    }

    private function run(array $command): void
    {
        $process = new Process($command, null, null, null, 3600);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(basename($command[0]).' failed: '.trim(mb_substr($process->getErrorOutput(), 0, 500)));
        }
    }
}
