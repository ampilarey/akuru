<?php

namespace App\Domains\Lending\Models;

use App\Domains\Lending\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One borrowing of one book (L1): asked, decided, handed over, returned. Carries the academic year (rule 10). */
class LendingLoan extends Model
{
    protected $fillable = [
        'lending_book_id', 'lender_id', 'borrower_user_id', 'academic_year_id', 'status', 'message', 'note',
        'requested_at', 'decided_at', 'handed_at', 'due_on', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'handed_at' => 'datetime',
            'due_on' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LendingBook::class, 'lending_book_id');
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }
}
