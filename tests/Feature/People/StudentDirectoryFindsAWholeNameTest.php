<?php

use App\Domains\People\Actions\ListStudentsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The directory's search matched each name column on its own, so a pupil's
 * whole name — the way an office types it — found nobody, while either half
 * found them. The sign-up walk (STATUS §5fq) typed the pupil's name into the
 * search box and got an empty page.
 */
it('finds a pupil by their whole name as well as by either half', function () {
    makeStudent(['first_name' => 'Fatima', 'last_name' => 'Yoosuf']);
    makeStudent(['first_name' => 'Fatima', 'last_name' => 'Ali']);

    $whole = app(ListStudentsAction::class)->execute(['search' => 'Fatima Yoosuf']);
    $half = app(ListStudentsAction::class)->execute(['search' => 'Fatima']);

    expect($whole)->toHaveCount(1)
        ->and($whole->first()->last_name)->toBe('Yoosuf')
        ->and($half)->toHaveCount(2);
});
