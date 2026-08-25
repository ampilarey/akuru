<?php

it('only RecordStaffAttendanceAction writes staff_attendance', function () {
    $root = dirname(__DIR__, 2);
    $allowed = [
        $root.'/app/Domains/HR/Actions/RecordStaffAttendanceAction.php',
        $root.'/database/migrations/2026_08_25_000021_s51_staff_attendance.php',
    ];

    $hits = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (in_array($path, $allowed, true)) {
            continue;
        }

        $contents = file_get_contents($path);
        if (preg_match('/DB::table\(\s*[\'"]staff_attendance[\'"]\s*\)/', $contents)) {
            $hits[] = $path;
        }
        if (preg_match('/StaffAttendance::query\(\)->(create|insert|update|upsert|updateOrCreate)/', $contents)) {
            $hits[] = $path;
        }
        if (preg_match('/StaffAttendance::(create|insert|updateOrCreate)\(/', $contents)) {
            $hits[] = $path;
        }
    }

    expect(array_values(array_unique($hits)))->toBe([]);
});
