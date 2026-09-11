<?php

namespace App\Domains\Circulation\Models;

use App\Domains\Circulation\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'academic_year_id', 'book_copy_id', 'student_id', 'borrower_user_id',
        'out_on', 'due_on', 'returned_on', 'issued_by', 'received_by', 'status', 'note',
    ];

    protected $casts = [
        'out_on' => 'date',
        'due_on' => 'date',
        'returned_on' => 'date',
        'status' => LoanStatus::class,
    ];

    public function scopeOut(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Out->value);
    }

    public function isOverdue(): bool
    {
        return $this->status === LoanStatus::Out
            && $this->due_on !== null
            && $this->due_on->isPast();
    }
}
