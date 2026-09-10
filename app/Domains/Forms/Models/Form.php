<?php

namespace App\Domains\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = [
        'created_by',
        'academic_year_id',
        'title',
        'description',
        'fields',
        'target_audience',
        'target_classes',
        'opens_at',
        'closes_at',
        'is_anonymous',
        'requires_parent_confirmation',
        'is_published',
    ];

    protected $casts = [
        'fields' => 'array',
        'target_audience' => 'array',
        'target_classes' => 'array',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'is_anonymous' => 'boolean',
        'requires_parent_confirmation' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /**
     * Open means published, started, and not yet closed.
     *
     * A form with no dates is open once published — the common case is "here is
     * the trip sheet, answer whenever", and demanding dates for that would make
     * every form a scheduling exercise.
     */
    public function isOpen(): bool
    {
        if (! $this->is_published) {
            return false;
        }

        $now = now();

        return ($this->opens_at === null || $this->opens_at->lte($now))
            && ($this->closes_at === null || $this->closes_at->gte($now));
    }
}
