<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROADMAP §S4 backlog: bank-statement import + auto-matching.
 *
 * Two tables, and the split matters. An **import** is the file somebody
 * uploaded, identified by the SHA-256 of its bytes so uploading the same export
 * twice is a no-op rather than a double set of lines. A **line** is one row of
 * that file, and carries its own hash so a re-export that overlaps the previous
 * period (banks do this constantly) does not duplicate the overlap.
 *
 * Rule 10: both record something that happens in time, so both carry
 * `academic_year_id`.
 *
 * Rule 12: a line is a record of what the bank says, never an authorisation.
 * `match_status` moves unmatched → suggested → confirmed, and **confirming is
 * what writes money** — through `RecordInvoiceReceiptAction`, the same audited
 * path a cashier uses, never by touching `invoices.paid_amount` here. Nothing
 * in this table grants access to anything: that still requires BML webhook
 * confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            // The file's own SHA-256. Unique, so re-uploading the same export is
            // idempotent instead of doubling every line in it.
            $table->string('source_hash', 64)->unique();
            $table->string('format', 32)->default('csv');
            $table->string('account_label')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->date('posted_on');
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            // Signed: credits positive, debits negative. Only credits can ever
            // match an invoice; debits are bank charges and outgoing transfers.
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            // Date + amount + description + reference, hashed. Scoped unique per
            // import so two genuinely identical transactions on one day (which
            // happen — two families paying the same fee) stay two lines, while a
            // re-imported overlap does not duplicate.
            $table->string('row_hash', 64);
            $table->string('match_status', 16)->default('unmatched')->index();
            $table->foreignId('matched_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->text('match_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_statement_import_id', 'row_hash'], 'bank_line_import_row_unique');
            $table->index(['posted_on', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
    }
};
