<?php

use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * KNOWN_ISSUES K2: a teacher's absence recorded in error can be removed from
 * its page — `absences.destroy` had no button.
 */
uses(RefreshDatabase::class);

it('offers Remove on an absence\'s page and removes it', function () {
    $office = User::factory()->create();
    $office->assignRole('admin');
    $absence = TeacherAbsence::query()->create([
        'teacher_id' => makeTeacherRow()->id,
        'from_date' => now()->toDateString(),
        'to_date' => now()->addDay()->toDateString(),
        'reason' => 'Recorded in error',
        'status' => 'pending',
        'created_by' => $office->id,
    ]);
    $web = test()->withoutLocalizationMiddleware()->actingAs($office);

    $web->get("/substitutions/absences/{$absence->id}/edit")->assertOk()
        ->assertSee('data-testid="remove-absence"', false)
        ->assertSee('action="'.route('absences.destroy', $absence).'"', false);
    $web->delete("/substitutions/absences/{$absence->id}")->assertRedirect(route('absences.index'))
        ->assertSessionHas('success', __('substitutions.flash_absence_removed'));
    expect(TeacherAbsence::query()->whereKey($absence->id)->exists())->toBeFalse();

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(__('substitutions.remove_absence', [], $locale))->not->toBe('substitutions.remove_absence')
            ->and(__('substitutions.remove_absence_confirm', [], $locale))->not->toBe('substitutions.remove_absence_confirm');
    }
});
