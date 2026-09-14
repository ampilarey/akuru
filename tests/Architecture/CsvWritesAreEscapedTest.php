<?php

/**
 * Every CSV row in `app/` goes through `App\Support\Csv`.
 *
 * ## What this is stopping
 *
 * Formula injection. A spreadsheet reads a cell starting `=`, `+`, `-`, `@`,
 * tab or carriage return as a formula, so a name typed into a registration
 * form becomes executable code on the desk of whoever opens the export. Before
 * this gate there were **236 `fputcsv` calls across 101 files and not one of
 * them escaped anything**.
 *
 * ## Why a gate rather than a fix
 *
 * The fix was mechanical and is already applied. The gate is the part that
 * lasts: the next export written will be written by copying a neighbouring
 * one, and this repo's own history says the neighbouring one is exactly how a
 * lesson fails to travel — see `SoftDeletesSurviveRawReadsTest`, where a
 * comment explaining a bug sat in one file for months while three other files
 * had the same bug.
 *
 * The check is deliberately crude: `fputcsv` may appear in `App\Support\Csv`
 * and nowhere else. There is no baseline, because there is no legitimate
 * reason to write an unescaped row — an export that genuinely wants a leading
 * `=` wants `Csv::cell()`'s numeric passthrough or a different file format.
 */
it('writes every CSV row through the escaping helper', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        // The helper is the one place the raw call belongs.
        if ($path === 'app/Support/Csv.php') {
            continue;
        }

        $source = stripPhpComments(file_get_contents($file->getPathname()));

        if (! preg_match_all('/\bfputcsv\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $offenders[] = $path.':'.(substr_count(substr($source, 0, $match[1]), "\n") + 1);
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        "These write a CSV row without escaping it:\n  "
        .implode("\n  ", $offenders)
        ."\n\nA cell starting `=`, `+`, `-`, `@`, tab or CR is a formula to Excel, "
        ."LibreOffice and Sheets, and almost every column in these exports is text "
        ."somebody typed. Use App\\Support\\Csv::put(\$handle, \$row) instead — it "
        .'escapes each cell and leaves genuine numbers alone.'
    );
});
