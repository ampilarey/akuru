<?php

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Staging had a closed "2026-2027" and an active "2026-2027 Pilot", both
 * spanning the calendar year. The resolver took the first year covering the
 * date, which was the closed one, so every date-scoped write on that host
 * landed in a year no screen showed by default (STATUS §5fz).
 */
it('prefers the active year when more than one covers the date', function () {
    $closed = makeYear(['name' => '2026-2027', 'status' => AcademicYearStatus::Closed, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $active = makeYear(['name' => '2026-2027 Pilot', 'status' => AcademicYearStatus::Active, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

    $resolved = app(ResolveAcademicYearForDateAction::class)->execute('2026-09-23');

    expect($resolved['id'])->toBe($active->id)
        ->and($resolved['id'])->not->toBe($closed->id);
});

it('still resolves a date only one year covers, whatever its status', function () {
    $last = makeYear(['name' => '2025-2026', 'status' => AcademicYearStatus::Closed, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
    makeYear(['name' => '2026-2027', 'status' => AcademicYearStatus::Active, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

    expect(app(ResolveAcademicYearForDateAction::class)->execute('2025-06-01')['id'])->toBe($last->id);
});

it('falls back to the active year for a date no year covers', function () {
    makeYear(['name' => '2025-2026', 'status' => AcademicYearStatus::Closed, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
    $active = makeYear(['name' => '2026-2027', 'status' => AcademicYearStatus::Active, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

    expect(app(ResolveAcademicYearForDateAction::class)->execute('2030-01-01')['id'])->toBe($active->id);
});
