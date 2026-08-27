<?php

/**
 * MySQL rejects identifiers longer than 64 chars (ER_TOO_LONG_IDENT), and a
 * too-long auto-generated index name fails the WHOLE migrate:fresh — which in
 * CI presents as every DB test slow-failing, indistinguishable from a hang.
 * This guard computes the names Laravel would generate for array-form
 * unique()/index() calls and foreignId() constraints inside Schema::create
 * blocks with literal table names, and fails fast with the offending file.
 */
it('keeps auto-generated migration index and FK names within MySQL\'s 64-char limit', function () {
    $violations = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
        $src = (string) file_get_contents($file);

        preg_match_all(
            "/Schema::create\\('([a-z0-9_]+)'.*?\\n(.*?)\\n        \\}\\);/s",
            $src,
            $tables,
            PREG_SET_ORDER,
        );

        foreach ($tables as [, $table, $body]) {
            $names = [];

            preg_match_all(
                "/->(unique|index)\\(\\[([^\\]]+)\\](?:,\\s*'[^']+')?\\)/",
                $body,
                $composites,
                PREG_SET_ORDER,
            );
            foreach ($composites as $composite) {
                if (str_contains($composite[0], ", '")) {
                    continue; // explicitly named
                }
                preg_match_all("/'([a-z0-9_]+)'/", $composite[2], $cols);
                $names[] = $table.'_'.implode('_', $cols[1]).'_'.$composite[1];
            }

            preg_match_all("/foreignId\\('([a-z0-9_]+)'\\)/", $body, $fks);
            foreach ($fks[1] as $column) {
                $names[] = $table.'_'.$column.'_foreign';
            }

            foreach ($names as $name) {
                if (strlen($name) > 64) {
                    $violations[] = basename($file).': '.$name.' ('.strlen($name).' chars)';
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
