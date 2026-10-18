<?php

use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * KNOWN_ISSUES (TS1): a substitution request now ends. The office closes a
 * covered one and cancels one no longer needed; a teacher can do neither.
 */
uses(RefreshDatabase::class);

function k1Request(string $status = 'open'): SubstitutionRequest
{
    return SubstitutionRequest::query()->create([
        'date' => now()->addDay()->toDateString(),
        'absent_teacher_id' => makeTeacherRow()->id,
        'subject_id' => makeSubject()->id,
        'classroom_id' => makeClass(makeYear())->id,
        'period_id' => makePeriodRow()->id,
        'status' => $status,
    ]);
}

function k1Office(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('closes an assigned request once it was covered, and keeps the assignment as the record', function () {
    $request = k1Request();
    $office = k1Office();
    $web = test()->withoutLocalizationMiddleware()->actingAs($office);

    $web->post("/substitutions/requests/{$request->id}/finish", ['outcome' => 'closed'])->assertSessionHasErrors('status');
    $web->post("/substitutions/requests/{$request->id}/assign", ['substitute_teacher_id' => makeTeacherRow()->id]);
    expect($request->fresh()->status)->toBe('assigned');

    $web->get("/substitutions/requests/{$request->id}")->assertOk()->assertSee('data-testid="close-request"', false);
    $web->post("/substitutions/requests/{$request->id}/finish", ['outcome' => 'closed'])->assertSessionHas('success', __('substitutions.flash_closed'));
    expect($request->fresh()->status)->toBe('closed')->and(SubstitutionAssignment::query()->where('substitution_request_id', $request->id)->exists())->toBeTrue();
    $web->get('/substitutions/requests?status=closed')->assertOk()->assertSee(route('substitutions.requests.show', $request), false);

    // Ended, it cannot be cancelled, edited or taken.
    $web->post("/substitutions/requests/{$request->id}/finish", ['outcome' => 'cancelled'])->assertSessionHasErrors('status');
    $web->get("/substitutions/requests/{$request->id}")->assertDontSee('data-testid="cancel-request"', false);
});

it('cancels an open request no longer needed, and lets only the office do it', function () {
    $request = k1Request();
    $teacher = makeTeacherRow();
    $teacherUser = User::query()->findOrFail($teacher->user_id);
    $teacherUser->assignRole('teacher');

    test()->withoutLocalizationMiddleware()->actingAs($teacherUser)->post("/substitutions/requests/{$request->id}/finish", ['outcome' => 'cancelled'])->assertForbidden();
    test()->withoutLocalizationMiddleware()->actingAs($teacherUser)->get("/substitutions/requests/{$request->id}")->assertDontSee('data-testid="cancel-request"', false);

    test()->withoutLocalizationMiddleware()->actingAs(k1Office())->post("/substitutions/requests/{$request->id}/finish", ['outcome' => 'cancelled'])->assertSessionHas('success', __('substitutions.flash_cancelled'));
    expect($request->fresh()->status)->toBe('cancelled')->and($request->fresh()->canBeTaken())->toBeFalse();
    test()->withoutLocalizationMiddleware()->actingAs(k1Office())->post("/substitutions/requests/{$request->id}/finish", ['outcome' => 'nonsense'])->assertSessionHasErrors('outcome');

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(__('substitutions.mark_covered', [], $locale))->not->toBe('substitutions.mark_covered');
    }
});
