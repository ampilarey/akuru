<?php

namespace App\Domains\Lending\Models;

use App\Domains\Lending\Enums\BookCondition;
use App\Domains\Lending\Enums\LendingBookStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A book someone offers to lend (L1). The deposit is words, never money (D4). */
class LendingBook extends Model
{
    protected $fillable = [
        'lender_id', 'slug', 'title', 'author', 'language', 'condition', 'description', 'grade', 'subject',
        'max_days', 'deposit', 'photo_media_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'condition' => BookCondition::class,
            'status' => LendingBookStatus::class,
            'max_days' => 'integer',
        ];
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LendingLoan::class);
    }
}
