<?php

/**
 * ADR-025's "guard to add in the deletion slice".
 *
 * F5 moved the Qur'an dataset (`Surah`, `QuranAyah`, `QuranMushaf`,
 * `QuranPage`, `QuranWord`, `QuranWordPosition`, `QuranTranslation`) out of
 * `Hifz\Models` and into `Courses\Components\Quran\Models`. The retired
 * direction must not come back: whatever remains of Hifz reads Qur'an
 * reference data through `App\Support\Contracts\QuranReferenceReader` /
 * `QuranTextProviderInterface`, never by importing the models.
 *
 * ADR-025 recorded why this needs its own test rather than the existing
 * scanners: `ViolationScanner::crossDomainModelViolators()` matches only
 * `App\Domains\X\Models\…`, so a `Components\Quran\Models\…` import is invisible
 * to it, and `crossDomainNonContractViolators()` would report each one as a
 * *new baseline entry* — which reads as "add it to the baseline" rather than
 * "this is forbidden". That blind spot is deliberate to leave in place and
 * covered here instead.
 */
$phpFilesUnder = function (string $relativeRoot): array {
    $root = dirname(__DIR__, 2).'/'.$relativeRoot;
    if (! is_dir($root)) {
        return [];
    }

    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname())] = file_get_contents($file->getPathname());
        }
    }

    return $files;
};

it('never lets Hifz reach back into the moved Quran dataset', function () use ($phpFilesUnder) {
    $violations = [];

    foreach ($phpFilesUnder('app/Domains/Hifz') as $path => $source) {
        // Both spellings: a `use` statement and an inline FQCN. Writing the
        // import as an FQCN is how this rule would be evaded by accident.
        if (preg_match_all('/App\\\\+Domains\\\\+Courses\\\\+Components\\\\+Quran\\\\+Models\\\\+(\w+)/', $source, $matches)) {
            foreach (array_unique($matches[1]) as $model) {
                $violations[] = $path.' -> Components\\Quran\\Models\\'.$model;
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Hifz reads Qur'an reference data through Support contracts, never the models (ADR-025):\n"
        .implode("\n", $violations)
    );
});

it('keeps the whole Quran dataset in the engine component', function () use ($phpFilesUnder) {
    $dataset = ['Surah', 'QuranAyah', 'QuranMushaf', 'QuranPage', 'QuranWord', 'QuranWordPosition', 'QuranTranslation'];

    $models = $phpFilesUnder('app/Domains/Courses/Components/Quran/Models');
    $present = [];
    foreach (array_keys($models) as $path) {
        $present[] = basename($path, '.php');
    }

    expect(array_values(array_intersect($dataset, $present)))->toBe($dataset);

    // …and nowhere else. Rule 11: one Qur'an dataset, one owner.
    $strays = [];
    foreach ($phpFilesUnder('app/Domains/Hifz/Models') as $path => $source) {
        if (in_array(basename($path, '.php'), $dataset, true)) {
            $strays[] = $path;
        }
    }

    expect($strays)->toBeEmpty('The Qur\'an dataset must not exist in two namespaces: '.implode(', ', $strays));
});
