<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LinkedAccount extends Model
{
    protected $fillable = ['user_id', 'linked_user_id', 'verified_at'];

    protected $casts = ['verified_at' => 'datetime'];

    /** Only a proved link is a link. */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }
}
