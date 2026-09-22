<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `status` is the one answer to "which year is active" (S1.5 rule 2, ADR-037).
 *
 * The pre-S1 `is_current` flag is still on the table because Blade screens and
 * a month of fixtures write it, but it is no longer a second source of truth:
 * a `saving` hook keeps the two in step whichever one a writer touched, and
 * every reader in the application asks for `status`. The column goes in a
 * later cleanup once nothing writes it either.
 */
class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'description',
        'status',
    ];

    protected $attributes = [
        'status' => 'upcoming',
        'is_current' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (AcademicYear $year): void {
            $active = $year->status === AcademicYearStatus::Active;

            if ($year->isDirty('status') || ! $year->isDirty('is_current')) {
                // `status` was set (or neither was): the flag follows it.
                $year->is_current = $active;

                return;
            }

            // Only the legacy flag was set — a seeder, a fixture, an old
            // screen. Raising it means active; lowering it demotes an active
            // year to upcoming and leaves a closed year closed.
            if ($year->is_current) {
                $year->status = AcademicYearStatus::Active;
            } elseif ($active) {
                $year->status = AcademicYearStatus::Upcoming;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'status' => AcademicYearStatus::class,
        ];
    }

    /**
     * @param  Builder<AcademicYear>  $query
     * @return Builder<AcademicYear>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AcademicYearStatus::Active);
    }

    public function termRecords(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }
}
