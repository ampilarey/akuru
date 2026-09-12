<?php

use App\Domains\Finance\Actions\ConfirmBankStatementMatchAction;
use App\Domains\Finance\Actions\IgnoreBankStatementLineAction;
use App\Domains\Finance\Actions\ImportBankStatementAction;
use App\Domains\Finance\Enums\BankStatementMatchStatus;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\BankStatementLine;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ROADMAP §S4 backlog — bank-statement import and matching.
 *
 * What these tests can honestly prove: the parser reads what the configured
 * column map says it will, importing is idempotent both ways, matching
 * proposes without deciding, and confirming writes money through the ordinary
 * audited path and nowhere else.
 *
 * What they **cannot** prove is that a real BML export looks anything like the
 * fixtures here. Nobody has seen one. That is why the column map is config and
 * why ADR-030 records the assumption rather than burying it.
 */
uses(RefreshDatabase::class);

function bankStatementActor(array $permissions = ['finance.manage', 'finance.record-manual-payment']): User
{
    $role = Role::findOrCreate('admin', 'web');
    foreach ($permissions as $name) {
        $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    $user = User::factory()->create(['name' => 'Finance Officer']);
    $user->assignRole('admin');

    return $user->fresh();
}

function makeInvoice(string $number, float $total, float $paid = 0, ?int $createdBy = null): Invoice
{
    // `invoices.student_id` is NOT NULL — an invoice is always somebody's.
    // Looked up rather than cached in a `static`: that survives the PHP
    // process but not `RefreshDatabase`'s truncation, so the second test in the
    // file inherits an id that no longer exists.
    $studentId = \Illuminate\Support\Facades\DB::table('students')->value('id')
        ?? makeStudent(['first_name' => 'Billed', 'last_name' => 'Pupil'])->id;

    return Invoice::query()->create([
        'invoice_number' => $number,
        'student_id' => $studentId,
        // Read off the enum, not guessed: an invented value inserts cleanly
        // and throws only when the model casts it back.
        'invoice_type' => InvoiceType::SchoolFees->value,
        'issue_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'currency' => 'MVR',
        'status' => InvoiceStatus::Sent->value,
        'subtotal' => $total,
        'total_amount' => $total,
        'paid_amount' => $paid,
        // NOT NULL as well: every invoice records who raised it.
        'created_by' => $createdBy ?? \App\Domains\Identity\Models\User::query()->value('id'),
    ]);
}

function statementCsv(array $rows): string
{
    $csv = "date,description,reference,amount\n";
    foreach ($rows as $row) {
        $csv .= implode(',', $row)."\n";
    }

    return $csv;
}

it('imports the rows the configured column map describes', function () {
    $actor = bankStatementActor();
    makeYear(['name' => 'Statement year', 'is_current' => true, 'status' => 'active']);

    $result = app(ImportBankStatementAction::class)->execute(
        statementCsv([
            ['2026-09-01', 'TRANSFER FROM AHMED', 'INV-0001', '1500.00'],
            ['2026-09-02', 'BANK CHARGE', '', '-25.00'],
        ]),
        'september.csv',
        $actor->id,
    );

    expect($result['created'])->toBe(2)
        ->and($result['duplicate_file'])->toBeFalse()
        ->and($result['import']->period_start?->toDateString())->toBe('2026-09-01')
        ->and($result['import']->period_end?->toDateString())->toBe('2026-09-02');

    // Rule 10: a statement line happens in time and carries the backbone. It
    // is nullable on purpose — a bank statement is the bank's record, and
    // refusing to import one because the school has not created that academic
    // year yet would be hostile — but when a year covers the date it is filled.
    expect(BankStatementLine::query()->whereNull('academic_year_id')->count())->toBe(0);

    $charge = BankStatementLine::query()->where('description', 'BANK CHARGE')->firstOrFail();
    expect($charge->isCredit())->toBeFalse();
});

it('treats re-uploading the same file as a no-op rather than a second copy', function () {
    $actor = bankStatementActor();
    $csv = statementCsv([['2026-09-01', 'TRANSFER', 'INV-0001', '1500.00']]);

    $first = app(ImportBankStatementAction::class)->execute($csv, 'september.csv', $actor->id);
    $second = app(ImportBankStatementAction::class)->execute($csv, 'september-again.csv', $actor->id);

    expect($second['duplicate_file'])->toBeTrue()
        ->and($second['import']->id)->toBe($first['import']->id)
        ->and(BankStatementLine::query()->count())->toBe(1);
});

it('does not duplicate a period that overlaps inside one upload', function () {
    $actor = bankStatementActor();

    // Banks re-export overlapping windows constantly; the identical row must
    // collapse, and it is the row hash rather than the file hash doing it here.
    $result = app(ImportBankStatementAction::class)->execute(
        statementCsv([
            ['2026-09-01', 'TRANSFER', 'INV-0001', '1500.00'],
            ['2026-09-01', 'TRANSFER', 'INV-0001', '1500.00'],
        ]),
        'overlap.csv',
        $actor->id,
    );

    expect($result['created'])->toBe(1);
});

it('suggests the invoice whose number appears in the statement text', function () {
    $actor = bankStatementActor();
    $invoice = makeInvoice('INV-0007', 1500);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-01', 'TRF REF INV-0007 AHMED', '', '900.00']]),
        'named.csv',
        $actor->id,
    );

    $line = BankStatementLine::query()->firstOrFail();

    // Note the amount does NOT match the balance: the reference is the stronger
    // evidence and wins on its own.
    expect($line->match_status)->toBe(BankStatementMatchStatus::Suggested)
        ->and($line->matched_invoice_id)->toBe($invoice->id);
});

it('suggests on an exact amount when exactly one invoice fits', function () {
    $actor = bankStatementActor();
    $invoice = makeInvoice('INV-0010', 2500);
    makeInvoice('INV-0011', 999);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-03', 'INWARD TRANSFER', '', '2500.00']]),
        'amount.csv',
        $actor->id,
    );

    expect(BankStatementLine::query()->firstOrFail()->matched_invoice_id)->toBe($invoice->id);
});

it('refuses to guess when two invoices share the same balance', function () {
    $actor = bankStatementActor();
    makeInvoice('INV-0020', 2500);
    makeInvoice('INV-0021', 2500);

    $result = app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-03', 'INWARD TRANSFER', '', '2500.00']]),
        'ambiguous.csv',
        $actor->id,
    );

    $line = BankStatementLine::query()->firstOrFail();

    // Two families owing the same termly fee is the ordinary case. Picking one
    // would put money on the wrong child, so the line stays unmatched and says
    // why.
    expect($result['ambiguous'])->toBe(1)
        ->and($line->match_status)->toBe(BankStatementMatchStatus::Unmatched)
        ->and($line->matched_invoice_id)->toBeNull()
        ->and($line->match_note)->toContain('INV-0020');
});

it('records a transfer receipt through the ordinary path when a person confirms', function () {
    $actor = bankStatementActor();
    $invoice = makeInvoice('INV-0030', 1200);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-04', 'TRF INV-0030', '', '1200.00']]),
        'confirm.csv',
        $actor->id,
    );

    $line = BankStatementLine::query()->firstOrFail();
    $receipt = app(ConfirmBankStatementMatchAction::class)->execute($line, $actor->id);

    // `transfer`, never `bml`: a statement line is the bank saying money
    // arrived, not a gateway webhook, and recording it as one would make the
    // reconciliation report lie about how the school was paid.
    expect($receipt->method)->toBe(ReceiptMethod::Transfer)
        ->and((float) $receipt->amount)->toBe(1200.0)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(1200.0)
        ->and($line->fresh()->match_status)->toBe(BankStatementMatchStatus::Confirmed)
        ->and($line->fresh()->receipt_id)->toBe($receipt->id);
});

it('never confirms the same line twice', function () {
    $actor = bankStatementActor();
    makeInvoice('INV-0040', 800);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-05', 'TRF INV-0040', '', '800.00']]),
        'double.csv',
        $actor->id,
    );
    $line = BankStatementLine::query()->firstOrFail();
    app(ConfirmBankStatementMatchAction::class)->execute($line, $actor->id);

    // The second receipt would be real money against a real invoice.
    expect(fn () => app(ConfirmBankStatementMatchAction::class)->execute($line->fresh(), $actor->id))
        ->toThrow(ValidationException::class);

    expect(Receipt::query()->count())->toBe(1);
});

it('credits only up to the balance and leaves the surplus unplaced', function () {
    $actor = bankStatementActor();
    $invoice = makeInvoice('INV-0050', 500);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-06', 'TRF INV-0050 TWO TERMS', '', '1300.00']]),
        'over.csv',
        $actor->id,
    );

    $line = BankStatementLine::query()->firstOrFail();
    $receipt = app(ConfirmBankStatementMatchAction::class)->execute($line, $actor->id);

    // A family paying two invoices with one transfer is common; guessing where
    // the surplus goes is how money lands on the wrong child.
    expect((float) $receipt->amount)->toBe(500.0)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(500.0)
        ->and($line->fresh()->match_note)->toContain('remainder is unplaced');
});

it('refuses to pay an invoice with a debit line', function () {
    $actor = bankStatementActor();
    makeInvoice('INV-0060', 300);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-07', 'BANK CHARGE', '', '-30.00']]),
        'debit.csv',
        $actor->id,
    );
    $line = BankStatementLine::query()->firstOrFail();

    expect(fn () => app(ConfirmBankStatementMatchAction::class)->execute($line, $actor->id, makeInvoice('INV-0061', 30)->id))
        ->toThrow(ValidationException::class);
});

it('keeps an ignored line on the record instead of deleting it', function () {
    $actor = bankStatementActor();

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-08', 'INTEREST', '', '2.15']]),
        'ignore.csv',
        $actor->id,
    );
    $line = BankStatementLine::query()->firstOrFail();

    $ignored = app(IgnoreBankStatementLineAction::class)->execute($line, $actor->id, 'Bank interest.');

    expect($ignored->match_status)->toBe(BankStatementMatchStatus::Ignored)
        ->and($ignored->match_note)->toBe('Bank interest.')
        ->and(BankStatementLine::query()->count())->toBe(1);
});

it('will not let a confirmed line be ignored away', function () {
    $actor = bankStatementActor();
    makeInvoice('INV-0070', 400);

    app(ImportBankStatementAction::class)->execute(
        statementCsv([['2026-09-09', 'TRF INV-0070', '', '400.00']]),
        'confirmed-ignore.csv',
        $actor->id,
    );
    $line = BankStatementLine::query()->firstOrFail();
    app(ConfirmBankStatementMatchAction::class)->execute($line, $actor->id);

    // Undoing money is a refund, with its own event and audit trail.
    expect(fn () => app(IgnoreBankStatementLineAction::class)->execute($line->fresh(), $actor->id))
        ->toThrow(ValidationException::class);
});
