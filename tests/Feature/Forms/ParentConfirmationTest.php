<?php

use App\Domains\Forms\Actions\ConfirmFormResponseAction;
use App\Domains\Forms\Actions\ListFormResponsesAction;
use App\Domains\Forms\Actions\ListFormsForUserAction;
use App\Domains\Forms\Actions\ListPendingConfirmationsAction;
use App\Domains\Forms\Actions\SaveFormAction;
use App\Domains\Forms\Actions\SubmitFormResponseAction;
use App\Domains\Forms\Models\FormResponse;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E6b — a pupil's answer waits for a guardian.
 *
 * A child ticking "yes, I am going on the trip" is not the same fact as their
 * parent agreeing to it. A results table that conflates the two sends children
 * on coaches their families never approved.
 */
function seedConfirmableForm(): array
{
    $student = makeStudent();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father');

    $form = app(SaveFormAction::class)->execute([
        'title' => 'Trip sign-up',
        'fields' => [['label' => 'Attending?', 'type' => 'yes_no', 'required' => true]],
        'is_published' => true,
        'requires_parent_confirmation' => true,
    ], (int) User::factory()->create()->id);

    $key = $form->fields[0]['key'];
    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) $student->user_id, [$key => 'yes'], ['student']);

    return [
        'form' => $form,
        'student' => $student,
        'guardian' => $guardian,
        'response' => FormResponse::query()->firstOrFail(),
    ];
}

it('refuses a form that is both anonymous and needs confirming', function () {
    // An anonymous answer has nobody to confirm for; shipping this combination
    // would create forms that quietly never become confirmable.
    app(SaveFormAction::class)->execute([
        'title' => 'Impossible',
        'fields' => [['label' => 'Q', 'type' => 'text']],
        'is_anonymous' => true,
        'requires_parent_confirmation' => true,
    ], (int) User::factory()->create()->id);
})->throws(ValidationException::class);

it('leaves a pupils answer unconfirmed on submission', function () {
    ['response' => $response] = seedConfirmableForm();

    expect($response->confirmed_at)->toBeNull()
        ->and($response->confirmed_by_user_id)->toBeNull();
});

it('tells the pupil their answer is still waiting', function () {
    ['student' => $student] = seedConfirmableForm();

    // Otherwise the form looks finished to them and nobody chases the parent.
    $row = app(ListFormsForUserAction::class)->execute((int) $student->user_id, ['student'])->first();

    expect($row['awaiting_confirmation'])->toBeTrue()
        ->and($row['answered_at'])->not->toBeNull();
});

it('puts the answer in the guardians pending queue', function () {
    ['guardian' => $guardian, 'student' => $student] = seedConfirmableForm();

    $pending = app(ListPendingConfirmationsAction::class)->execute((int) $guardian->user_id);

    expect($pending)->toHaveCount(1)
        ->and($pending->first()['form_title'])->toBe('Trip sign-up')
        ->and($pending->first()['child_name'])->toContain($student->first_name);
});

it('lets the guardian confirm', function () {
    ['guardian' => $guardian, 'response' => $response] = seedConfirmableForm();

    $confirmed = app(ConfirmFormResponseAction::class)
        ->execute((int) $response->id, (int) $guardian->user_id);

    expect($confirmed->confirmed_at)->not->toBeNull()
        // Never confirmed with nobody accountable for having confirmed it.
        ->and((int) $confirmed->confirmed_by_user_id)->toBe((int) $guardian->user_id)
        ->and(app(ListPendingConfirmationsAction::class)->count((int) $guardian->user_id))->toBe(0);
});

it('refuses to let a pupil confirm their own answer', function () {
    ['student' => $student, 'response' => $response] = seedConfirmableForm();

    // The rule that gives the feature its point, and the one easiest to lose:
    // if the same identity can both answer and confirm, the confirmation
    // records nothing. Enforced on identity, not on session, so E7's account
    // switcher cannot route around it.
    app(ConfirmFormResponseAction::class)->execute((int) $response->id, (int) $student->user_id);
})->throws(ValidationException::class);

it('refuses a confirmation from someone elses guardian', function () {
    ['response' => $response] = seedConfirmableForm();

    $stranger = makeGuardian();
    app(AttachGuardianAction::class)->execute(makeStudent(), $stranger, 'mother');

    app(ConfirmFormResponseAction::class)->execute((int) $response->id, (int) $stranger->user_id);
})->throws(ValidationException::class);

it('refuses a confirmation from an unrelated account', function () {
    ['response' => $response] = seedConfirmableForm();

    app(ConfirmFormResponseAction::class)
        ->execute((int) $response->id, (int) User::factory()->create()->id);
})->throws(ValidationException::class);

it('refuses to confirm on a form that never asked for it', function () {
    $student = makeStudent();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father');

    $form = app(SaveFormAction::class)->execute([
        'title' => 'Plain survey',
        'fields' => [['label' => 'Q', 'type' => 'text']],
        'is_published' => true,
    ], (int) User::factory()->create()->id);
    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) $student->user_id, [$form->fields[0]['key'] => 'a'], ['student']);

    app(ConfirmFormResponseAction::class)
        ->execute((int) FormResponse::query()->firstOrFail()->id, (int) $guardian->user_id);
})->throws(ValidationException::class);

it('shows staff which answers are confirmed and which are not', function () {
    ['form' => $form, 'guardian' => $guardian, 'response' => $response] = seedConfirmableForm();

    $before = app(ListFormResponsesAction::class)->execute((int) $form->id);
    expect($before['form']['requires_parent_confirmation'])->toBeTrue()
        ->and($before['form']['confirmed'])->toBe(0)
        // Acting on unconfirmed answers is the mistake this prevents, so the
        // results table must never present them as equivalent.
        ->and($before['rows']->first()['confirmed_at'])->toBe('');

    app(ConfirmFormResponseAction::class)->execute((int) $response->id, (int) $guardian->user_id);

    $after = app(ListFormResponsesAction::class)->execute((int) $form->id);
    expect($after['form']['confirmed'])->toBe(1)
        ->and($after['rows']->first()['confirmed_at'])->not->toBe('');
});

it('leaves a re-answered response needing confirmation again', function () {
    ['form' => $form, 'student' => $student, 'guardian' => $guardian, 'response' => $response] =
        seedConfirmableForm();
    app(ConfirmFormResponseAction::class)->execute((int) $response->id, (int) $guardian->user_id);

    // Changing the answer after confirmation would otherwise carry the parent's
    // approval across to something they never saw.
    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id,
        (int) $student->user_id,
        [$form->fields[0]['key'] => 'no'],
        ['student'],
    );

    expect(FormResponse::query()->firstOrFail()->confirmed_at)->toBeNull();
});

it('offers nothing to confirm for a guardian with no children', function () {
    seedConfirmableForm();

    expect(app(ListPendingConfirmationsAction::class)->execute((int) User::factory()->create()->id))
        ->toBeEmpty();
});
