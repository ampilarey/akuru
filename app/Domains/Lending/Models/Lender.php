<?php

namespace App\Domains\Lending\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who lends their own books (LENDING_AND_USED_BOOKS_PLAN L1). One
 * row per user; the name and island are what borrowers see. Their books go
 * public once the office has checked their ID card (decision D5), which the
 * Identity domain holds — nothing here copies it.
 */
class Lender extends Model
{
    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    protected $fillable = ['user_id', 'display_name', 'island', 'about', 'id_required', 'status'];

    protected function casts(): array
    {
        return ['id_required' => 'boolean'];
    }

    public function books(): HasMany
    {
        return $this->hasMany(LendingBook::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LendingLoan::class);
    }
}
