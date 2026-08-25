<?php

/**
 * 1A.7 — harden engine boundaries beyond the Phase 0 baseline lists.
 */
it('courses does not import Media, Progress, or People models', function () {
    $root = base_path('app/Domains/Courses');
    $violations = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (preg_match('/App\\\\Domains\\\\(Media|Progress|People)\\\\Models\\\\/', $contents)) {
            $violations[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});

it('progress does not import other domain models', function () {
    $root = base_path('app/Domains/Progress');
    $violations = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (preg_match('/App\\\\Domains\\\\(?!Progress\\\\)[A-Za-z]+\\\\Models\\\\/', $contents)) {
            $violations[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});
