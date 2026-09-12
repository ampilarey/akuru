<?php

use App\Domains\Finance\Contracts\BankStatementParserInterface;
use App\Domains\Finance\Services\ConfiguredCsvBankStatementParser;
use Illuminate\Validation\ValidationException;

/**
 * The parser is the part of this feature most likely to be wrong in production,
 * because it is the only part written against a format nobody has seen. These
 * tests pin the behaviour that has to survive the day a real BML export
 * arrives: that the column map is honoured, that both amount shapes work, and
 * that an unreadable file says **what it found** rather than failing blankly.
 */
it('binds the configured csv parser by default', function () {
    expect(app(BankStatementParserInterface::class))
        ->toBeInstanceOf(ConfiguredCsvBankStatementParser::class);
});

it('reads a bank\'s own column names from config without a code change', function () {
    // The whole reason the map is config: this is a plausible export from a
    // bank that calls things something else entirely.
    config()->set('finance.bank_statement.columns', [
        'date' => 'Txn Date',
        'description' => 'Narrative',
        'reference' => 'Cheque/Ref',
        'amount' => null,
        'credit' => 'Deposit',
        'debit' => 'Withdrawal',
    ]);
    config()->set('finance.bank_statement.date_formats', ['d/m/Y']);

    $rows = (new ConfiguredCsvBankStatementParser)->parse(
        "Txn Date,Narrative,Cheque/Ref,Deposit,Withdrawal\n"
        ."03/09/2026,SALARY TRANSFER,INV-9,\"1,250.50\",\n"
        ."04/09/2026,SERVICE FEE,,,25.00\n"
    );

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->postedOn)->toBe('2026-09-03')
        ->and($rows[0]->amount)->toBe(1250.50)
        ->and($rows[0]->reference)->toBe('INV-9')
        // A withdrawal is stored negative so the sign convention matches the
        // single-signed-column shape, and the matcher only looks at credits.
        ->and($rows[1]->amount)->toBe(-25.0);
});

it('reads day-first dates as day-first', function () {
    config()->set('finance.bank_statement.date_formats', ['d/m/Y', 'Y-m-d']);

    $rows = (new ConfiguredCsvBankStatementParser)->parse(
        "date,description,reference,amount\n03/04/2026,TRANSFER,,100.00\n"
    );

    // 03/04/2026 parses cleanly under both conventions, so format order is the
    // only thing deciding whether this is April or March. Getting it backwards
    // silently misdates every line in the file.
    expect($rows[0]->postedOn)->toBe('2026-04-03');
});

it('handles accounting negatives and stray currency symbols', function () {
    $rows = (new ConfiguredCsvBankStatementParser)->parse(
        "date,description,reference,amount\n"
        ."2026-09-01,CORRECTION,,(45.00)\n"
        ."2026-09-02,TRANSFER,,\"MVR 2,000.00\"\n"
    );

    expect($rows[0]->amount)->toBe(-45.0)
        ->and($rows[1]->amount)->toBe(2000.0);
});

it('skips zero-value statement artefacts', function () {
    $rows = (new ConfiguredCsvBankStatementParser)->parse(
        "date,description,reference,amount\n"
        ."2026-09-01,BALANCE BROUGHT FORWARD,,0.00\n"
        ."2026-09-02,TRANSFER,,10.00\n"
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->description)->toBe('TRANSFER');
});

it('names the columns it actually found when it cannot read the file', function () {
    // The failure a real export will hit first. A blank "invalid file" would
    // leave somebody guessing; naming both sides turns it into a config edit.
    expect(fn () => (new ConfiguredCsvBankStatementParser)->parse("foo,bar\n1,2\n"))
        ->toThrow(ValidationException::class);

    try {
        (new ConfiguredCsvBankStatementParser)->parse("foo,bar\n1,2\n");
    } catch (ValidationException $e) {
        expect($e->errors()['file'][0])->toContain('foo, bar');
    }
});

it('rejects an empty file and a header with no rows', function () {
    expect(fn () => (new ConfiguredCsvBankStatementParser)->parse(''))
        ->toThrow(ValidationException::class);

    expect(fn () => (new ConfiguredCsvBankStatementParser)->parse("date,description,reference,amount\n"))
        ->toThrow(ValidationException::class);
});

it('gives the same row the same hash and different rows different ones', function () {
    $rows = (new ConfiguredCsvBankStatementParser)->parse(
        "date,description,reference,amount\n"
        ."2026-09-01,TRANSFER,REF1,10.00\n"
        ."2026-09-01,TRANSFER,REF1,10.00\n"
        ."2026-09-01,TRANSFER,REF2,10.00\n"
    );

    expect($rows[0]->hash())->toBe($rows[1]->hash())
        ->and($rows[0]->hash())->not->toBe($rows[2]->hash());
});
