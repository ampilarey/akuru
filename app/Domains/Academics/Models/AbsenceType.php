<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AbsenceType extends Model
{
    protected $fillable = [
        'code', 'name', 'name_dhivehi', 'name_arabic',
        'excuses_absence', 'requires_evidence', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'excuses_absence' => 'boolean',
        'requires_evidence' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** What a family may choose today. */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
