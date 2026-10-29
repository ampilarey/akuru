<?php

namespace App\Support\Services\Backup;

use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * BK1: makes one backup file — the database (mysqldump, one consistent
 * transaction, gzipped as it streams) and the uploaded and private files
 * (tar), with a manifest, bundled and encrypted into `akuru-….akb`. The
 * password goes to mysqldump in its environment, never on its command line.
 */
final class BackupMaker
{
    public function __construct(private BackupSettings $settings) {}

    /**
     * @return array{name: string, path: string, size: int, database_bytes: int, files_bytes: int, tables: int}
     */
    public function make(?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now('Indian/Maldives');
        $name = BackupNames::make($at);
        $work = $this->settings->workDir($name);
        try {
            $sql = $work.'/database.sql.gz';
            $tables = $this->dumpDatabase($sql);
            $files = $work.'/files.tar.gz';
            $this->archiveFiles($files);
            file_put_contents($work.'/manifest.json', json_encode([
                'made_at' => $at->toIso8601String(),
                'app' => config('app.name'),
                'database' => $this->settings->database()['database'],
                'tables' => $tables,
                'database_bytes' => filesize($sql),
                'files_bytes' => filesize($files),
                'format' => 1,
            ], JSON_PRETTY_PRINT));
            $bundle = $work.'/bundle.tar';
            $this->run([$this->settings->binary('tar'), '-cf', $bundle, '-C', $work, 'manifest.json', 'database.sql.gz', 'files.tar.gz']);
            $local = $this->settings->local();
            $target = $local->path($name);
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            BackupCipher::encrypt($bundle, $target, $this->settings->passphrase());
            @chmod($target, 0600);

            return ['name' => $name, 'path' => $target, 'size' => (int) filesize($target), 'database_bytes' => (int) filesize($sql), 'files_bytes' => (int) filesize($files), 'tables' => $tables];
        } finally {
            self::removeDir($work);
        }
    }

    /** @return int the tables dumped */
    private function dumpDatabase(string $out): int
    {
        $db = $this->settings->database();
        $command = [$this->settings->binary('mysqldump'), ...$this->settings->connectionArgs(),
            '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces', '--hex-blob', '--default-character-set=utf8mb4',
            ...$this->settings->mysqldumpOptions(), $db['database']];
        $gz = gzopen($out, 'wb6');
        $errors = '';
        $tables = 0;
        $process = new Process($command, null, ['MYSQL_PWD' => (string) $db['password']], null, 3600);
        $process->run(function (string $type, string $buffer) use ($gz, &$errors, &$tables) {
            if ($type === Process::ERR) {
                $errors .= $buffer;

                return;
            }
            if (@gzwrite($gz, $buffer) !== strlen($buffer)) {
                $errors .= 'Could not write the database dump: the disk may be full. ';
            }
            $tables += substr_count($buffer, 'CREATE TABLE ');
        });
        gzclose($gz);
        if (! $process->isSuccessful() || str_contains($errors, 'Could not write')) {
            throw new RuntimeException('mysqldump failed: '.trim(mb_substr($errors, 0, 500)));
        }

        return $tables;
    }

    private function archiveFiles(string $out): void
    {
        $root = $this->settings->filesRoot();
        if (! is_dir($root)) {
            $this->run([$this->settings->binary('tar'), '-czf', $out, '-T', '/dev/null']);

            return;
        }
        $exclude = array_map(fn (string $e) => '--exclude=./'.ltrim($e, './'), $this->settings->filesExclude());
        // GNU tar exits 1 when a file changes while it is read (a log being written): that copy is still good.
        $this->run([$this->settings->binary('tar'), '-czf', $out, ...$exclude, '-C', $root, '.'], [0, 1]);
    }

    /** @param list<int> $ok */
    private function run(array $command, array $ok = [0]): void
    {
        $process = new Process($command, null, null, null, 3600);
        $process->run();
        if (! in_array($process->getExitCode(), $ok, true)) {
            throw new RuntimeException(basename($command[0]).' failed: '.trim(mb_substr($process->getErrorOutput(), 0, 500)));
        }
    }

    public static function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
