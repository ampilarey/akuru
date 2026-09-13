<?php

namespace App\Domains\Offerings\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnforceSeatLimitAction
{
    public const OUTCOME_RESERVED = 'reserved';

    public const OUTCOME_WAITLISTED = 'waitlisted';

    /**
     * Lock the resource row and occupying occupancy rows, then decide reserved vs waitlisted.
     *
     * Callers must insert occupancy in the same (outer) DB transaction so InnoDB
     * row locks hold until the occupying row exists.
     *
     * @param  list<string>  $occupyingStatuses
     * @param  bool  $respectSoftDeletes  Skip rows the occupancy table has soft-deleted.
     *                                    This counts with the query builder rather than
     *                                    Eloquent — deliberately, for the row locks — and
     *                                    the query builder knows nothing about `SoftDeletes`.
     *                                    A removed enrolment held its seat forever.
     * @return array{outcome: string, row: object, waitlist_position: int|null, taken: int, limit: int|null}
     */
    public function execute(
        string $resourceTable,
        int $resourceId,
        string $limitColumn,
        string $occupancyTable,
        string $foreignKey,
        array $occupyingStatuses,
        ?string $waitlistEnabledColumn = null,
        string $fullMessage = 'No remaining seats.',
        string $waitlistStatus = 'waitlisted',
        bool $respectSoftDeletes = false,
    ): array {
        return DB::transaction(function () use (
            $resourceTable,
            $resourceId,
            $limitColumn,
            $occupancyTable,
            $foreignKey,
            $occupyingStatuses,
            $waitlistEnabledColumn,
            $fullMessage,
            $waitlistStatus,
            $respectSoftDeletes,
        ): array {
            $row = DB::table($resourceTable)->where('id', $resourceId)->lockForUpdate()->first();
            if ($row === null) {
                throw ValidationException::withMessages([
                    $foreignKey => 'Resource not found.',
                ]);
            }

            $rawLimit = $row->{$limitColumn} ?? null;
            $limit = $rawLimit === null || $rawLimit === '' ? null : (int) $rawLimit;

            $taken = (int) DB::table($occupancyTable)
                ->where($foreignKey, $resourceId)
                ->whereIn('status', $occupyingStatuses)
                ->when($respectSoftDeletes, fn ($query) => $query->whereNull('deleted_at'))
                ->lockForUpdate()
                ->count();

            if ($limit === null || $taken < $limit) {
                return [
                    'outcome' => self::OUTCOME_RESERVED,
                    'row' => $row,
                    'waitlist_position' => null,
                    'taken' => $taken,
                    'limit' => $limit,
                ];
            }

            $waitlistEnabled = $waitlistEnabledColumn !== null && (bool) $row->{$waitlistEnabledColumn};

            if ($waitlistEnabled) {
                $waitlisted = (int) DB::table($occupancyTable)
                    ->where($foreignKey, $resourceId)
                    ->where('status', $waitlistStatus)
                    ->when($respectSoftDeletes, fn ($query) => $query->whereNull('deleted_at'))
                    ->lockForUpdate()
                    ->count();

                return [
                    'outcome' => self::OUTCOME_WAITLISTED,
                    'row' => $row,
                    'waitlist_position' => $waitlisted + 1,
                    'taken' => $taken,
                    'limit' => $limit,
                ];
            }

            throw ValidationException::withMessages([
                $foreignKey => $fullMessage,
            ]);
        });
    }
}
