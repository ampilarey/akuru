<?php

/**
 * `fake()->unique()` does not do what it reads like.
 *
 * It returns a **fresh** `UniqueGenerator` on every call, each with its own
 * empty memory, so it guarantees uniqueness only within a single call — which
 * is to say, never. A fixture written as
 * `fake()->unique()->numerify('###')` against a unique column is three random
 * digits: a thousand possible values, and a birthday collision waiting.
 *
 * That is not theoretical. It reddened CI on PR #290 with
 * `Duplicate entry 'ARB101' for key 'subjects.subjects_code_unique'` — on a
 * commit whose only changes were in an unrelated domain, and which passed
 * locally. The fixtures had been flaky since they were written; adding tests
 * elsewhere shifted the random sequence and the coin finally landed badly.
 *
 * A test suite that fails once a fortnight for no visible reason trains people
 * to re-run CI instead of reading it, which is the real cost. Use
 * `uniqueFixtureSuffix()`.
 */
it('never reaches for fake()->unique(), which guarantees nothing', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        // This file necessarily contains the string it forbids — in its own
        // test name and in the matcher itself.
        if ($file->getFilename() === 'NoFakeUniqueInFixturesTest.php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        // The explanation above necessarily names the thing it forbids, so the
        // guard skips lines that are comments.
        foreach (explode("\n", $contents) as $number => $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            if (str_contains($line, 'fake()->unique()')) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});
