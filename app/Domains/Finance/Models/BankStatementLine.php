<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\BankStatementMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_statement_import_id', 'posted_on', 'description', 'reference',
        'amount', 'currency', 'row_hash', 'match_status', 'matched_invoice_id',
        'receipt_id', 'match_note', 'decided_by', 'decided_at', 'academic_year_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
            'amount' => 'decimal:2',
            'match_status' => BankStatementMatchStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'matched_invoice_id');
    }

    /** Only money coming in can ever pay an invoice. */
    public function isCredit(): bool
    {
        return (float) $this->amount > 0;
    }
}
