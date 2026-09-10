<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Forms\Actions\ListFormsForUserAction;
use App\Domains\Forms\Actions\SaveFormAction;
use App\Domains\Forms\Actions\SubmitFormResponseAction;
use App\Domains\Forms\Models\FormResponse;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E6c — a sign-up that costs money.
 *
 * The invoice is raised through Finance's own action (rule 11: one invoice
 * system) and paid through the portal's existing BML flow, so **money rule 12
 * holds by construction** — nothing here confirms a payment, and access still
 * follows the webhook rather than a return URL.
 */
function makePaidForm(array $overrides = []): \App\Domains\Forms\Models\Form
{
    return app(SaveFormAction::class)->execute(array_merge([
        'title' => 'Field trip',
        'fields' => [['label' => 'Attending?', 'type' => 'yes_no', 'required' => true]],
        'is_published' => true,
        'fee_amount' => 150,
    ], $overrides), (int) User::factory()->create()->id);
}

it('refuses a fee on an anonymous form', function () {
    // An invoice is student-scoped and an anonymous answer records nobody, so
    // there is nobody to bill.
    makePaidForm(['is_anonymous' => true]);
})->throws(ValidationException::class);

it('raises one invoice for a pupil who signs up', function () {
    $form = makePaidForm();
    $student = makeStudent();

    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id,
        (int) $student->user_id,
        [$form->fields[0]['key'] => 'yes'],
        ['student'],
    );

    $invoice = Invoice::query()->firstOrFail();

    expect(Invoice::query()->count())->toBe(1)
        ->and((int) $invoice->student_id)->toBe((int) $student->id)
        ->and((float) $invoice->total_amount)->toBe(150.0)
        // Traceable back to what raised it, rather than appearing from nowhere
        // on a family's statement.
        ->and($invoice->meta['source'])->toBe('form')
        ->and((int) $invoice->meta['source_id'])->toBe((int) $form->id)
        ->and((int) FormResponse::query()->firstOrFail()->invoice_id)->toBe((int) $invoice->id);
});

it('raises nothing for a free form', function () {
    $form = makePaidForm(['fee_amount' => null]);
    $student = makeStudent();

    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $student->user_id, [$form->fields[0]['key'] => 'yes'], ['student'],
    );

    expect(Invoice::query()->count())->toBe(0);
});

it('does not bill a family twice for changing their answer', function () {
    $form = makePaidForm();
    $student = makeStudent();
    $key = $form->fields[0]['key'];
    $submit = app(SubmitFormResponseAction::class);

    $submit->execute((int) $form->id, (int) $student->user_id, [$key => 'yes'], ['student']);
    $submit->execute((int) $form->id, (int) $student->user_id, [$key => 'no'], ['student']);

    // The existing invoice is left alone rather than cancelled and re-made —
    // a family may already be part-way through paying it.
    expect(Invoice::query()->count())->toBe(1);
});

it('bills the guardians only eligible child without asking', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $inClass = makeStudent();
    $notInClass = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $inClass->id);

    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($inClass, $guardian, 'father');
    app(AttachGuardianAction::class)->execute($notInClass, $guardian, 'father');

    $form = makePaidForm(['target_classes' => [$class->id]]);

    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $guardian->user_id, [$form->fields[0]['key'] => 'yes'], ['parent'],
    );

    // A parent of three with one child in Grade 5 should not have to choose.
    expect((int) Invoice::query()->firstOrFail()->student_id)->toBe((int) $inClass->id);
});

it('refuses to guess when a guardian has two eligible children', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $guardian = makeGuardian();
    foreach ([makeStudent(), makeStudent()] as $child) {
        app(AssignStudentToClassAction::class)->execute($class, (int) $child->id);
        app(AttachGuardianAction::class)->execute($child, $guardian, 'father');
    }

    $form = makePaidForm(['target_classes' => [$class->id]]);

    // Guessing is how the wrong family gets billed.
    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $guardian->user_id, [$form->fields[0]['key'] => 'yes'], ['parent'],
    );
})->throws(ValidationException::class);

it('accepts the child a guardian names', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $guardian = makeGuardian();
    $children = [makeStudent(), makeStudent()];
    foreach ($children as $child) {
        app(AssignStudentToClassAction::class)->execute($class, (int) $child->id);
        app(AttachGuardianAction::class)->execute($child, $guardian, 'father');
    }

    $form = makePaidForm(['target_classes' => [$class->id]]);

    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $guardian->user_id, [$form->fields[0]['key'] => 'yes'], ['parent'],
        (int) $children[1]->id,
    );

    expect((int) Invoice::query()->firstOrFail()->student_id)->toBe((int) $children[1]->id);
});

it('refuses a guardian naming a child that is not theirs', function () {
    $form = makePaidForm();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute(makeStudent(), $guardian, 'father');

    // Never trust the posted id.
    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $guardian->user_id, [$form->fields[0]['key'] => 'yes'], ['parent'],
        (int) makeStudent()->id,
    );
})->throws(ValidationException::class);

it('refuses a paid sign-up from an account with no pupil at all', function () {
    $form = makePaidForm();

    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) User::factory()->create()->id, [$form->fields[0]['key'] => 'yes'], [],
    );
})->throws(ValidationException::class);

it('freezes the price once families have been invoiced', function () {
    $form = makePaidForm();
    $student = makeStudent();
    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $student->user_id, [$form->fields[0]['key'] => 'yes'], ['student'],
    );

    app(SaveFormAction::class)->execute([
        'title' => 'Field trip',
        'fields' => [['label' => 'Attending?', 'type' => 'yes_no']],
        'is_published' => true,
        'fee_amount' => 900,
    ], (int) $form->created_by, $form);

    // Changing it would bill later families differently for the same trip.
    expect((float) $form->refresh()->fee_amount)->toBe(150.0);
});

it('reads paid state from the invoice rather than a copy', function () {
    $form = makePaidForm();
    $student = makeStudent();
    app(SubmitFormResponseAction::class)->execute(
        (int) $form->id, (int) $student->user_id, [$form->fields[0]['key'] => 'yes'], ['student'],
    );

    $row = app(ListFormsForUserAction::class)->execute((int) $student->user_id, ['student'])->first();
    expect($row['invoice_status'])->toBe('sent');

    // Two records of whether a family has paid is one more than a school can
    // reconcile, so the response stores no paid flag of its own.
    Invoice::query()->firstOrFail()->update(['status' => 'paid']);

    expect(app(ListFormsForUserAction::class)->execute((int) $student->user_id, ['student'])->first()['invoice_status'])
        ->toBe('paid');
});
