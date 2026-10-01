<?php

namespace App\Domains\Lending\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One side's word on the other after a return (L2): stars and a few words. One per person per loan. */
class LendingRating extends Model
{
    public const ABOUT_LENDER = 'lender';

    public const ABOUT_BORROWER = 'borrower';

    protected $fillable = ['lending_loan_id', 'lender_id', 'by_user_id', 'about_user_id', 'about', 'stars', 'comment'];

    protected function casts(): array
    {
        return ['stars' => 'integer'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LendingLoan::class, 'lending_loan_id');
    }
}
