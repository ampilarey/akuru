<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Finance\Contracts\BankStatementParserInterface;
use App\Domains\Finance\DTOs\BankStatementRow;
use App\Domains\Finance\Enums\BankStatementMatchStatus;
use App\Domains\Finance\Models\BankStatementImport;
use App\Domains\Finance\Models\BankStatementLine;
use Illuminate\Support\Facades\DB;

/**
 * Reads a statement file into `bank_statement_lines`, then asks the matcher for
 * suggestions. It never confirms one — see `ConfirmBankStatementMatchAction`.
 *
 * Idempotent twice over, because both kinds of duplicate happen in practice:
 * re-uploading the identical file (the `source_hash` unique index turns it into
 * a no-op returning the original import), and uploading a fresh export whose
 * period overlaps one already loaded (`row_hash`, unique per import, keeps the
 * overlap from doubling inside a single upload).
 */
class ImportBankStatementAction
{
    public function __construct(private BankStatementParserInterface $parser) {}

    /**
     * @return array{import: BankStatementImport, created: int, duplicate_file: bool, suggested: int, ambiguous: int}
     */
    public function execute(string $contents, string $filename, ?int $importedBy = null, ?string $accountLabel = null): array
    {
        $hash = hash('sha256', $contents);

        $existing = BankStatementImport::query()->where('source_hash', $hash)->first();
        if ($existing !== null) {
            // Deliberately not an error. Uploading the same export twice is an
            // ordinary human slip, and answering "already loaded, here it is"
            // is more useful than a validation failure.
            return [
                'import' => $existing,
                'created' => 0,
                'duplicate_file' => true,
                'suggested' => 0,
                'ambiguous' => 0,
            ];
        }

        /** @var list<BankStatementRow> $rows */
        $rows = $this->parser->parse($contents);

        $dates = array_map(fn (BankStatementRow $row): string => $row->postedOn, $rows);
        sort($dates);

        $import = DB::transaction(function () use ($rows, $dates, $hash, $filename, $importedBy, $accountLabel) {
            $import = BankStatementImport::query()->create([
                'original_filename' => $filename,
                'source_hash' => $hash,
                'format' => (string) config('finance.bank_statement.driver', 'csv'),
                'account_label' => $accountLabel,
                'period_start' => $dates[0] ?? null,
                'period_end' => $dates[count($dates) - 1] ?? null,
                'line_count' => 0,
                'academic_year_id' => $this->yearFor($dates[0] ?? null),
                'imported_by' => $importedBy,
            ]);

            $created = 0;
            foreach ($rows as $row) {
                $line = BankStatementLine::query()->firstOrCreate(
                    [
                        'bank_statement_import_id' => $import->id,
                        'row_hash' => $row->hash(),
                    ],
                    [
                        'posted_on' => $row->postedOn,
                        'description' => $row->description,
                        'reference' => $row->reference,
                        'amount' => $row->amount,
                        'currency' => $row->currency,
                        'match_status' => BankStatementMatchStatus::Unmatched->value,
                        'academic_year_id' => $this->yearFor($row->postedOn),
                    ]
                );

                if ($line->wasRecentlyCreated) {
                    $created++;
                }
            }

            $import->line_count = $created;
            $import->save();

            return $import;
        });

        $matches = app(SuggestBankStatementMatchesAction::class)->execute($import->id);

        return [
            'import' => $import->fresh(),
            'created' => $import->line_count,
            'duplicate_file' => false,
            'suggested' => $matches['suggested'],
            'ambiguous' => $matches['ambiguous'],
        ];
    }

    private function yearFor(?string $date): ?int
    {
        if ($date === null) {
            return null;
        }

        $year = app(ResolveAcademicYearForDateAction::class)->execute($date);

        return $year === null ? null : (int) $year['id'];
    }
}
