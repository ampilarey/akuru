<?php

use App\Domains\Academics\Actions\ResolveDefaultTermPeriodAction;
use App\Domains\Academics\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prefers the active term dates for a year', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    makeTerm($year, 'Upcoming');
    $upcoming = Term::query()->where('academic_year_id', $year->id)->where('name', 'Upcoming')->sole();
    $upcoming->update(['status' => 'upcoming', 'sort_order' => 1, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']);

    $active = makeTerm($year, 'Active');
    $active->update(['sort_order' => 2, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']);

    expect(app(ResolveDefaultTermPeriodAction::class)->execute($year->id))->toBe([
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
    ]);
});
