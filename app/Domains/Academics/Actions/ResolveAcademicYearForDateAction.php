<?php

namespace App\Domains\Academics\Actions;

class ResolveAcademicYearForDateAction
{
    /**
     * The year a date belongs to. When more than one year's range covers the
     * date — staging had a closed "2026-2027" and an active "2026-2027 Pilot"
     * both spanning the calendar year (STATUS §5fz) — the active one wins,
     * then the current one, then the first listed. Taking the first match
     * regardless sent every date-scoped write on that host — a staff
     * check-in, an approved leave, a form, a poll, a sensitive note — into
     * the closed year, where no screen defaulting to the active year could
     * see it.
     *
     * @return array<string, mixed>|null
     */
    public function execute(?string $date = null): ?array
    {
        $date ??= now('Indian/Maldives')->toDateString();
        $years = app(ListAcademicYearsAction::class)->execute();

        $covering = $years->filter(
            fn (array $year): bool => ($year['start_date'] ?? '') <= $date && ($year['end_date'] ?? '') >= $date
        );

        $match = $covering->firstWhere('status', 'active')
            ?? $covering->firstWhere('is_current', true)
            ?? $covering->first();

        if ($match !== null) {
            return $match;
        }

        return $years->firstWhere('is_current', true)
            ?? $years->firstWhere('status', 'active');
    }
}
