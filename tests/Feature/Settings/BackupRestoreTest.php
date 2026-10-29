<?php

use App\Domains\Settings\Actions\RunBackupAction;
use App\Domains\Settings\Models\BackupRun;
use App\Support\Services\Backup\BackupCipher;
use App\Support\Services\Backup\BackupMaker;
use App\Support\Services\Backup\BackupNames;
use App\Support\Services\Backup\BackupRestorer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * SPEC "Backups": "A backup is not considered valid unless the restore
 * procedure has been tested." (BK1, STATUS §5up.) This file is that test:
 * it backs up the real test database and a folder of files, restores both
 * somewhere else, and checks they came back — on every CI run.
 *
 * The test's own writes sit in RefreshDatabase's open transaction, which a
 * dump cannot see, so the marker row is written on a second connection.
 */
uses(RefreshDatabase::class);

function bk1Folders(): array
{
    $base = storage_path('framework/testing/bk1-'.bin2hex(random_bytes(4)));
    $files = $base.'/files';
    mkdir($files.'/private/media', 0777, true);
    file_put_contents($files.'/private/media/report-card.html', '<p>Aishath — 87</p>');
    mkdir($files.'/backups', 0777, true);
    file_put_contents($files.'/backups/old.akb', 'must not be inside a backup');
    config([
        'backup.local_path' => $base.'/kept',
        'backup.files_root' => $files,
        'backup.passphrase' => 'correct horse battery staple',
        'backup.disk' => null,
    ]);

    return [$base, $files];
}

/** A connection of its own, so what it writes is committed and a dump sees it. */
function bk1Side(?string $database = null): \Illuminate\Database\Connection
{
    $name = 'bk1_side_'.($database ?? 'live');
    config(['database.connections.'.$name => array_merge(config('database.connections.'.config('database.default')), $database === null ? [] : ['database' => $database])]);
    DB::purge($name);

    return DB::connection($name);
}

it('backs up the database and the files, and restores both elsewhere — the restore SPEC asks to be tested', function () {
    [$base] = bk1Folders();
    $marker = 'bk1_marker_'.bin2hex(random_bytes(4));
    $live = (string) config('database.connections.'.config('database.default').'.database');
    $scratch = $live.'_bk1';
    $side = bk1Side();
    $side->table('settings')->insert(['key' => $marker, 'value' => 'restored', 'created_at' => now(), 'updated_at' => now()]);
    $side->statement('DROP DATABASE IF EXISTS `'.$scratch.'`');
    $side->statement('CREATE DATABASE `'.$scratch.'`');
    try {
        $run = app(RunBackupAction::class)->execute();
        expect($run->status)->toBe('succeeded', (string) $run->error)
            ->and($run->name)->toMatch('/^akuru-\d{8}-\d{6}\.akb$/')
            ->and($run->tables)->toBeGreaterThan(100)
            ->and(is_file($base.'/kept/'.$run->name))->toBeTrue()
            // Encrypted: no table name readable in the file.
            ->and(str_contains((string) file_get_contents($base.'/kept/'.$run->name), 'CREATE TABLE'))->toBeFalse();

        $this->artisan('akuru:backup-restore', ['name' => $run->name, '--into' => $scratch, '--files' => $base.'/restored'])->assertSuccessful();

        $restored = bk1Side($scratch);
        $count = fn ($connection, string $db) => (int) $connection->selectOne('select count(*) as n from information_schema.tables where table_schema = ?', [$db])->n;
        expect($count($restored, $scratch))->toBe($count($side, $live))
            ->and($restored->table('settings')->where('key', $marker)->value('value'))->toBe('restored')
            ->and(file_get_contents($base.'/restored/private/media/report-card.html'))->toBe('<p>Aishath — 87</p>')
            // The backups folder is never inside a backup.
            ->and(is_file($base.'/restored/backups/old.akb'))->toBeFalse();
    } finally {
        $side->table('settings')->where('key', $marker)->delete();
        $side->statement('DROP DATABASE IF EXISTS `'.$scratch.'`');
        BackupMaker::removeDir($base);
    }
});

it('refuses the wrong passphrase and a file cut short', function () {
    $dir = storage_path('framework/testing/bk1-cipher-'.bin2hex(random_bytes(4)));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/plain', str_repeat('wallet ledger row ', 200000));
    BackupCipher::encrypt($dir.'/plain', $dir.'/sealed', 'right');
    BackupCipher::decrypt($dir.'/sealed', $dir.'/opened', 'right');
    expect(file_get_contents($dir.'/opened'))->toBe(file_get_contents($dir.'/plain'));

    expect(fn () => BackupCipher::decrypt($dir.'/sealed', $dir.'/x', 'wrong'))->toThrow(RuntimeException::class);
    // A full disk stops it with a reason, never a short file that looks finished (found backing up the dev database).
    if (is_writable('/dev/full')) {
        expect(fn () => BackupCipher::encrypt($dir.'/plain', '/dev/full', 'right'))->toThrow(RuntimeException::class, 'disk may be full');
    }
    file_put_contents($dir.'/cut', substr((string) file_get_contents($dir.'/sealed'), 0, 1048576));
    expect(fn () => BackupCipher::decrypt($dir.'/cut', $dir.'/y', 'right'))->toThrow(RuntimeException::class);
    BackupMaker::removeDir($dir);
});

it('copies off-site when a store is set, and keeps 7 daily, 4 weekly and 6 monthly copies', function () {
    [$base] = bk1Folders();
    Storage::fake('backups');
    config(['backup.disk' => 'backups']);
    try {
        $run = app(RunBackupAction::class)->execute();
        expect($run->status)->toBe('succeeded', (string) $run->error)->and($run->offsite)->toBeTrue()
            ->and(Storage::disk('backups')->exists($run->name))->toBeTrue();
        // What was copied off-site restores too.
        @unlink($base.'/kept/'.$run->name);
        $opened = app(BackupRestorer::class)->open($run->name);
        expect($opened['manifest']['tables'])->toBe($run->tables);
        app(BackupRestorer::class)->close($opened['work']);
    } finally {
        BackupMaker::removeDir($base);
    }

    // 1 January to 30 April 2026, one a day: keep the last seven days, the
    // newest of the weeks of 13 and 19 April, and the last of January to March.
    $names = [];
    for ($day = CarbonImmutable::create(2026, 1, 1, 3, 0, 0, 'Indian/Maldives'); $day->lte(CarbonImmutable::create(2026, 4, 30, 3, 0, 0, 'Indian/Maldives')); $day = $day->addDay()) {
        $names[] = BackupNames::make($day);
    }
    $gone = BackupNames::toDelete([...$names, 'notes.txt'], 7, 4, 6);
    $kept = array_values(array_diff($names, $gone));
    expect(count($names))->toBe(120)
        ->and($kept)->toBe(['akuru-20260131-030000.akb', 'akuru-20260228-030000.akb', 'akuru-20260331-030000.akb', 'akuru-20260412-030000.akb', 'akuru-20260419-030000.akb',
            'akuru-20260424-030000.akb', 'akuru-20260425-030000.akb', 'akuru-20260426-030000.akb', 'akuru-20260427-030000.akb', 'akuru-20260428-030000.akb', 'akuru-20260429-030000.akb', 'akuru-20260430-030000.akb'])
        ->and($gone)->not->toContain('notes.txt');
});

it('records a failed run with why, and the command says so', function () {
    [$base] = bk1Folders();
    config(['backup.binaries.mysqldump' => '/nonexistent/mysqldump']);
    try {
        $this->artisan('akuru:backup')->assertFailed();
        $run = BackupRun::query()->latest('id')->sole();
        expect($run->status)->toBe('failed')->and($run->error)->toContain('mysqldump')
            ->and(glob($base.'/kept/*.akb') ?: [])->toBe([]);
    } finally {
        BackupMaker::removeDir($base);
    }
});

it('runs every night at three, Maldives time', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'akuru:backup'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 3 * * *')->and($event->timezone)->toBe('Indian/Maldives');
});
