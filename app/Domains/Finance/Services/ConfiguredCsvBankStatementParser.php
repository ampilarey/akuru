<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Contracts\BankStatementParserInterface;
use App\Domains\Finance\DTOs\BankStatementRow;
use Illuminate\Validation\ValidationException;

/**
 * A CSV parser whose column names come from `config('finance.bank_statement')`
 * rather than from this file.
 *
 * That indirection is the whole point. Nobody here has seen a real BML export,
 * so hardcoding "Date, Description, Credit, Debit" would be a guess dressed up
 * as an implementation — and the guess would be discovered only when somebody
 * uploaded a genuine statement and got a validation error or, far worse, a
 * silently mis-parsed amount. With the map in config, the first real export is
 * absorbed by editing `.env`, and any file this parser cannot read fails loudly
 * naming the columns it did find.
 *
 * Amount handling supports both shapes banks use: a single signed column, or
 * separate credit and debit columns. Which one applies is config, not a sniff.
 */
class ConfiguredCsvBankStatementParser implements BankStatementParserInterface
{
    /**
     * @return list<BankStatementRow>
     */
    public function parse(string $contents): array
    {
        $map = (array) config('finance.bank_statement.columns', []);
        $currency = (string) config('finance.bank_statement.currency', 'MVR');
        $dateFormats = (array) config('finance.bank_statement.date_formats', ['Y-m-d']);

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if ($lines === [] || trim((string) $lines[0]) === '') {
            throw ValidationException::withMessages(['file' => 'The statement file is empty.']);
        }

        $header = array_map(
            fn (string $column): string => strtolower(trim($column, " \t\n\r\0\x0B\"'\xEF\xBB\xBF")),
            str_getcsv((string) array_shift($lines)) ?: []
        );

        $dateKey = $this->require($header, $map['date'] ?? 'date', 'date');
        $amountKey = $this->optional($header, $map['amount'] ?? 'amount');
        $creditKey = $this->optional($header, $map['credit'] ?? 'credit');
        $debitKey = $this->optional($header, $map['debit'] ?? 'debit');

        if ($amountKey === null && $creditKey === null && $debitKey === null) {
            throw ValidationException::withMessages([
                'file' => 'No amount column found. Expected one of '
                    .implode(', ', array_filter([$map['amount'] ?? 'amount', $map['credit'] ?? 'credit', $map['debit'] ?? 'debit']))
                    .'; the file has: '.implode(', ', $header).'.',
            ]);
        }

        $descriptionKey = $this->optional($header, $map['description'] ?? 'description');
        $referenceKey = $this->optional($header, $map['reference'] ?? 'reference');

        $rows = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);
            $row = [];
            foreach ($header as $offset => $key) {
                $row[$key] = $cells[$offset] ?? null;
            }

            $postedOn = $this->parseDate((string) ($row[$dateKey] ?? ''), $dateFormats, $index);
            $amount = $this->parseAmount($row, $amountKey, $creditKey, $debitKey);

            // A zero-value line is a statement artefact (a balance marker, a
            // carried-forward row), not a transaction. Skipping beats importing
            // noise a human then has to dismiss one row at a time.
            if ($amount === 0.0) {
                continue;
            }

            $rows[] = new BankStatementRow(
                postedOn: $postedOn,
                amount: $amount,
                description: $this->text($row, $descriptionKey),
                reference: $this->text($row, $referenceKey),
                currency: $currency,
            );
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The statement had a header but no transaction rows.']);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $header
     */
    private function require(array $header, string $wanted, string $label): string
    {
        $key = $this->optional($header, $wanted);
        if ($key === null) {
            throw ValidationException::withMessages([
                'file' => "No {$label} column found. Expected '{$wanted}'; the file has: ".implode(', ', $header).'.',
            ]);
        }

        return $key;
    }

    /**
     * @param  list<string>  $header
     */
    private function optional(array $header, ?string $wanted): ?string
    {
        if ($wanted === null || $wanted === '') {
            return null;
        }
        $wanted = strtolower(trim($wanted));

        return in_array($wanted, $header, true) ? $wanted : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function text(array $row, ?string $key): ?string
    {
        if ($key === null) {
            return null;
        }
        $value = trim((string) ($row[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string>  $formats
     */
    private function parseDate(string $raw, array $formats, int $index): string
    {
        $raw = trim($raw);
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $raw);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        throw ValidationException::withMessages([
            'file' => 'Could not read the date "'.$raw.'" on row '.($index + 2)
                .'. Configured formats: '.implode(', ', $formats).'.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function parseAmount(array $row, ?string $amountKey, ?string $creditKey, ?string $debitKey): float
    {
        if ($amountKey !== null) {
            return $this->number($row[$amountKey] ?? null);
        }

        // Credit and debit columns: exactly one is normally filled per row.
        // Debits are stored negative so the sign convention is the same either
        // way, and the matcher only ever looks at credits.
        $credit = $creditKey !== null ? $this->number($row[$creditKey] ?? null) : 0.0;
        $debit = $debitKey !== null ? $this->number($row[$debitKey] ?? null) : 0.0;

        return round($credit - abs($debit), 2);
    }

    private function number(mixed $raw): float
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return 0.0;
        }

        // Thousands separators and currency symbols are common in exports;
        // parentheses are the accounting convention for a negative.
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '';
        $number = $value === '' ? 0.0 : (float) $value;

        return round($negative ? -abs($number) : $number, 2);
    }
}
